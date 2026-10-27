<?php

declare(strict_types=1);

use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Tests\NotesTestCase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/**
 * @param  array<string, string>  $files
 * @return array<string, mixed>
 */
function notePayload(array $files, array $extra = []): array
{
    return [...$extra, 'files' => array_map(
        fn (string $path, string $contents): array => ['path' => $path, 'content' => base64_encode($contents)],
        array_keys($files),
        $files,
    )];
}

function bearer(string $token = NotesTestCase::TOKEN): array
{
    return ['Authorization' => "Bearer {$token}"];
}

it('accepts only a bearer token whose sha256 is configured', function (): void {
    $cases = [
        'no token' => [[], 401],
        'wrong token' => [bearer('not-the-token'), 401],
        'right token' => [bearer(), 200],
    ];

    foreach ($cases as $case => [$headers, $status]) {
        getJson('/docs/_api/v1/notes', $headers)->assertStatus($status);
    }

    config()->set('ai-docs.api.tokens', []);

    getJson('/docs/_api/v1/notes', bearer())->assertUnauthorized();
});

it('stores a pushed page under its path, stamped with the push time and author', function (): void {
    $this->travelTo('2026-10-28 14:05:00');

    postJson('/docs/_api/v1/notes', notePayload(['flows/cart/price-recalc.md' => "# Price recalc\n\nBody.\n"], ['author' => 'Claude']), bearer())
        ->assertOk()
        ->assertJsonPath('panel', 'notes')
        ->assertJsonPath('pages.0.slug', 'flows/cart/price-recalc')
        ->assertJsonPath('pages.0.url', 'http://localhost/docs/notes/flows/cart/price-recalc');

    $stored = (string) file_get_contents($this->notesRoot().'/flows/cart/price-recalc.md');

    expect($stored)->toStartWith("---\n")
        ->toContain("author: Claude\n")
        ->toContain("updated: '2026-10-28T14:05:00+00:00'")
        ->toEndWith("# Price recalc\n\nBody.\n");
});

it('refuses a file that would land outside the panel or is not markdown or an image', function (): void {
    $cases = [
        'climbs out' => '../escape.md',
        'absolute' => '/etc/passwd.md',
        'hidden' => 'flows/.secret.md',
        'php' => 'flows/shell.php',
        'html' => 'flows/page.html',
    ];

    foreach ($cases as $case => $path) {
        postJson('/docs/_api/v1/notes', notePayload([$path => 'x']), bearer())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files.0.path');

        expect(is_dir($this->notesRoot()))->toBeFalse($case);
    }
});

it('404s a panel that does not take notes', function (): void {
    postJson('/docs/_api/v1/notes', notePayload(['a.md' => '# A']), bearer())->assertOk();

    $cases = ['docs', 'missing'];

    foreach ($cases as $panel) {
        postJson('/docs/_api/v1/notes', notePayload(['a.md' => '# A'], ['panel' => $panel]), bearer())->assertNotFound();
        getJson("/docs/_api/v1/notes?panel={$panel}", bearer())->assertNotFound();
    }
});

it('serves a pushed note in its panel behind the same gate, with its author', function (): void {
    postJson('/docs/_api/v1/notes', notePayload(['flows/order.md' => "# Order flow\n"], ['author' => 'Claude']), bearer())->assertOk();

    get('/docs/notes/flows/order')->assertRedirect(route('login'));

    actingAs(adUser())
        ->get('/docs/notes/flows/order')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page.slug', 'flows/order')
            ->where('page.author', 'Claude')
            ->where('page.path', '.ai/notes/flows/order.md')
            ->where('page.updated', fn (?string $updated): bool => $updated !== null)
            ->where('groups.0.label', 'Flows'));

    AiDocs::auth(fn (): bool => false);

    actingAs(adUser())->get('/docs/notes/flows/order')->assertForbidden();
});

it('registers neither the panels nor the token API when disabled', function (): void {
    NotesTestCase::$enabled = false;

    try {
        $this->refreshApplication();

        getJson('/docs/_api/v1/notes', bearer())->assertNotFound();
        get('/docs/notes')->assertNotFound();
    } finally {
        NotesTestCase::$enabled = true;
    }
});

it('passes the doctor with a note panel nobody has pushed to yet', function (): void {
    $this->artisan('ai-docs:doctor')
        ->expectsOutputToContain('0 notes · 1 API token(s)')
        ->doesntExpectOutputToContain('does not exist');
});
