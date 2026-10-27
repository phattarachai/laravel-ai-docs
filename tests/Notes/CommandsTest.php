<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Phattarachai\AiDocs\Support\FrontMatter;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

const NOTE_PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

const NOTE_PAGE = <<<'MD'
# คำนวณราคาใหม่ในตะกร้า

ราคาเปลี่ยนเมื่อ coupon หมดอายุ.

![Before and after](shots/cart.png)

```mermaid
flowchart LR
  A[Cart] --> B{Coupon valid?}
```

## ขั้นที่สอง

ส่วนลดคิดใหม่ทุกครั้ง.
MD;

function writeLocal(string $path, string $contents): string
{
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $contents);

    return $path;
}

it('round-trips a page and its images through push and pull', function (): void {
    $source = writeLocal($this->scratch().'/draft/price-recalc.md', NOTE_PAGE);
    writeLocal($this->scratch().'/draft/shots/cart.png', base64_decode(NOTE_PIXEL));

    artisan('ai-docs:push', ['source' => $source, '--path' => 'flows/cart/price-recalc'])
        ->expectsOutputToContain('http://localhost/docs/notes/flows/cart/price-recalc')
        ->assertSuccessful();

    expect(File::exists($this->notesRoot().'/flows/cart/shots/cart.png'))->toBeTrue();

    $to = $this->scratch().'/pulled';
    artisan('ai-docs:pull', ['note' => 'flows/cart/price-recalc', '--to' => $to])->assertSuccessful();

    [$meta, $body] = FrontMatter::split(File::get("{$to}/flows/cart/price-recalc.md"));

    expect($body)->toBe(NOTE_PAGE)
        ->and($meta['author'])->toBe('Claude')
        ->and($meta)->toHaveKey('updated')
        ->and(File::get("{$to}/flows/cart/shots/cart.png"))->toBe(base64_decode(NOTE_PIXEL));
});

it('renders a pushed note like a repo doc: category groups, image, mermaid and search', function (): void {
    $source = writeLocal($this->scratch().'/price-recalc.md', NOTE_PAGE);
    writeLocal($this->scratch().'/shots/cart.png', base64_decode(NOTE_PIXEL));

    artisan('ai-docs:push', ['source' => $source, '--path' => 'flows/cart/price-recalc'])->assertSuccessful();

    actingAs(adUser())
        ->get('/docs/notes/flows/cart/price-recalc')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.label', 'Flows')
            ->where('groups.0.groups.0.label', 'Cart')
            ->where('groups.0.groups.0.items.0.slug', 'flows/cart/price-recalc')
            ->where('page.html', fn (string $html): bool => str_contains($html, 'src="/docs/notes/_media/flows/cart/shots/cart.png"')
                && str_contains($html, 'class="doc-mermaid"')));

    actingAs(adUser())->get('/docs/notes/_media/flows/cart/shots/cart.png')->assertOk();

    $index = actingAs(adUser())->getJson('/docs/notes/_search.json')->assertOk()->json();

    expect($index[0]['slug'])->toBe('flows/cart/price-recalc')
        ->and(collect($index[0]['sections'])->pluck('text')->implode(' '))->toContain('ส่วนลดคิดใหม่ทุกครั้ง');
});

it('takes the panel and path from where the file sits in the working copy', function (): void {
    $source = writeLocal(base_path('.ai/notes/flows/order.md'), "# Order\n");

    artisan('ai-docs:push', ['source' => $source, '--title' => 'Order flow — from cart to paid'])->assertSuccessful();

    [$meta] = FrontMatter::split(File::get($this->notesRoot().'/flows/order.md'));

    expect($meta['title'])->toBe('Order flow — from cart to paid');
});

it('pulls a page URL into the working copy and keeps that copy out of git', function (): void {
    $gitignore = base_path('.gitignore');
    $original = File::exists($gitignore) ? File::get($gitignore) : null;
    writeLocal($this->scratch().'/order.md', "# Order\n");

    try {
        artisan('ai-docs:push', ['source' => $this->scratch().'/order.md', '--path' => 'flows/order'])->assertSuccessful();

        artisan('ai-docs:pull', ['note' => 'https://staging.example.com/docs/notes/flows/order#step-2'])->assertSuccessful();
        artisan('ai-docs:pull', ['note' => 'flows/order'])->assertSuccessful();

        expect(File::exists(base_path('.ai/notes/flows/order.md')))->toBeTrue()
            ->and(substr_count(File::get($gitignore), "/.ai/notes/\n"))->toBe(1);
    } finally {
        $original === null ? File::delete($gitignore) : File::put($gitignore, $original);
    }
});

it('adds to a pushed folder, and replaces it only with --prune', function (): void {
    $folder = $this->scratch().'/order';
    writeLocal("{$folder}/index.md", "---\ngroup: Order flow\n---\n# Overview\n");
    writeLocal("{$folder}/paid.md", "# Paid\n");
    writeLocal("{$folder}/notes.txt", 'not a page');

    artisan('ai-docs:push', ['source' => $folder, '--path' => 'flows/order'])->assertSuccessful();

    File::delete("{$folder}/paid.md");
    writeLocal("{$folder}/refund.md", "# Refund\n");

    artisan('ai-docs:push', ['source' => $folder, '--path' => 'flows/order'])->assertSuccessful();

    expect(File::exists($this->notesRoot().'/flows/order/paid.md'))->toBeTrue('a plain push keeps what the folder dropped')
        ->and(File::exists($this->notesRoot().'/flows/order/notes.txt'))->toBeFalse('only markdown and images travel');

    artisan('ai-docs:push', ['source' => $folder, '--path' => 'flows/order', '--prune' => true])->assertSuccessful();

    expect(File::exists($this->notesRoot().'/flows/order/paid.md'))->toBeFalse('--prune drops it')
        ->and(File::exists($this->notesRoot().'/flows/order/refund.md'))->toBeTrue();
});

it('lists, deletes and dry-runs', function (): void {
    $source = writeLocal($this->scratch().'/order.md', "# Order flow\n");

    artisan('ai-docs:push', ['source' => $source, '--path' => 'flows/order', '--dry-run' => true])->assertSuccessful();
    expect(File::exists($this->notesRoot().'/flows/order.md'))->toBeFalse('a dry run sends nothing');

    artisan('ai-docs:push', ['source' => $source, '--path' => 'flows/order'])->assertSuccessful();
    artisan('ai-docs:pull')->expectsOutputToContain('Order flow')->assertSuccessful();

    artisan('ai-docs:push', ['source' => 'flows/order', '--delete' => true])->assertSuccessful();

    expect(File::exists($this->notesRoot().'/flows'))->toBeFalse('the emptied category goes too');

    artisan('ai-docs:push', ['source' => 'flows/order', '--delete' => true])->assertFailed();
    artisan('ai-docs:pull', ['note' => 'flows/order', '--to' => $this->scratch()])->expectsOutputToContain('404')->assertFailed();
});

it('stops before sending a page whose image is missing or would leave the panel', function (): void {
    $cases = [
        'missing image' => ["# A\n\n![x](gone.png)\n", 'is not beside the page'],
        'climbs out' => ["# A\n\n![x](../../up.png)\n", 'would land outside the panel'],
    ];

    foreach ($cases as $case => [$markdown, $message]) {
        $source = writeLocal($this->scratch().'/page.md', $markdown);

        artisan('ai-docs:push', ['source' => $source, '--path' => 'flows/page'])->expectsOutputToContain($message)->assertFailed();
    }

    expect(File::exists($this->notesRoot()))->toBeFalse();
});

it('issues a token whose plaintext stays in the local .env', function (): void {
    $env = $this->app->environmentFilePath();
    $original = File::exists($env) ? File::get($env) : null;

    try {
        artisan('ai-docs:token', ['--write' => true])->expectsOutputToContain('AI_DOCS_TOKEN_HASHES=')->assertSuccessful();

        preg_match('/^AI_DOCS_REMOTE_TOKEN=(\S+)$/m', File::get($env), $match);

        expect($match[1] ?? '')->toHaveLength(64);
    } finally {
        $original === null ? File::delete($env) : File::put($env, $original);
    }
});
