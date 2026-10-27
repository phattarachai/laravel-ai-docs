<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Tests;

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Override;

/**
 * The docs panel plus a note panel at `/docs/notes`, with the token API accepting
 * {@see self::TOKEN} and the ai-docs:* commands pointed at this same app. Notes land
 * in `<testbench>/storage/app/ai-docs/notes`, wiped around every test.
 */
abstract class NotesTestCase extends TestCase
{
    public const string TOKEN = 'test-token-for-the-notes-api';

    /**
     * Routes are registered at boot, so turning the package off needs a fresh app.
     */
    public static bool $enabled = true;

    private ?string $scratch = null;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->wipe();
        $this->routeRemoteToApp();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->wipe();

        if ($this->scratch !== null) {
            File::deleteDirectory($this->scratch);
        }

        parent::tearDown();
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $config = $app['config'];

        $config->set('ai-docs.enabled', self::$enabled);
        $config->set('ai-docs.panels', [
            'docs' => ['root' => '.ai/documents', 'label' => 'Documents'],
            'notes' => ['path' => 'docs/notes', 'label' => 'Notes', 'notes' => true],
        ]);
        $config->set('ai-docs.api.tokens', [hash('sha256', self::TOKEN)]);
        $config->set('ai-docs.remote.url', 'http://localhost');
        $config->set('ai-docs.remote.token', self::TOKEN);
        $config->set('ai-docs.remote.author', 'Claude');
    }

    /**
     * A temporary folder for local files, removed after the test.
     */
    public function scratch(): string
    {
        $this->scratch ??= sys_get_temp_dir().'/ai-docs-notes-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->scratch);

        return $this->scratch;
    }

    public function notesRoot(): string
    {
        return base_path('storage/app/ai-docs/notes');
    }

    /**
     * Send what the commands' HTTP client sends through this app's kernel, so a
     * command test runs against the real API rather than a stub of it.
     */
    private function routeRemoteToApp(): void
    {
        Http::fake(function (ClientRequest $request) {
            $response = $this->call($request->method(), $request->url(), server: [
                'HTTP_AUTHORIZATION' => $request->header('Authorization')[0] ?? '',
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => $request->header('Content-Type')[0] ?? 'application/json',
            ], content: $request->body());

            return Http::response((string) $response->getContent(), $response->getStatusCode(), ['Content-Type' => 'application/json']);
        });
    }

    private function wipe(): void
    {
        File::deleteDirectory($this->notesRoot());
        File::deleteDirectory(base_path('.ai/notes'));
    }
}
