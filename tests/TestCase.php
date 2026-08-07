<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Tests;

use Illuminate\Support\Facades\Route;
use Inertia\ServiceProvider as InertiaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\AiDocsServiceProvider;
use Phattarachai\AiDocs\Tests\Fixtures\AdUser;

/**
 * A minimal Laravel host for the docs panel: sqlite, an authenticatable, a
 * `login` route to be redirected to, and an Inertia root view. Everything the
 * package assumes of its host and nothing more.
 *
 * `AiDocs::root()` is `base_path(config('ai-docs.root'))`, and the base path is
 * Testbench's skeleton inside `vendor/`. So `linkFixtures()` hangs the suite's
 * own markdown tree off that skeleton at `.ai/documents`, the package default —
 * which keeps `page.path` reading like a real project's, and keeps every
 * `realpath()` containment check honest.
 */
abstract class TestCase extends Orchestra
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // The gate is process-global; a test that registers its own callback
        // would otherwise leak into every test that runs after it.
        AiDocs::flushAuth();
        AiDocs::auth(fn ($request): bool => $request->user() !== null);
    }

    #[Override]
    protected function tearDown(): void
    {
        AiDocs::flushAuth();

        parent::tearDown();
    }

    /**
     * @return list<class-string>
     */
    #[Override]
    protected function getPackageProviders($app): array
    {
        return [InertiaServiceProvider::class, AiDocsServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $this->linkFixtures($app->basePath());

        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $config->set('auth.providers.users.model', AdUser::class);
        // The published page ships with the package, so `assertInertia`'s
        // "does this component exist?" check resolves without a host tree.
        // Inertia v3 reads `pages.*`; v2 read `testing.page_*`.
        $config->set('inertia.pages.paths', [__DIR__.'/../resources/js/pages']);
        $config->set('inertia.pages.extensions', ['jsx']);
        $config->set('inertia.testing.page_paths', [__DIR__.'/../resources/js/pages']);
        $config->set('inertia.testing.page_extensions', ['jsx']);
        $config->set('view.paths', [...(array) $config->get('view.paths', []), __DIR__.'/Fixtures/views']);

        // Pin the panel's own config: the shipped file is a tunable an app owner
        // edits, so the suite must not assert against whatever it says.
        $config->set('ai-docs.root', '.ai/documents');
        $config->set('ai-docs.exclude', ['private']);
        $config->set('ai-docs.cache', value: false);
        $config->set('ai-docs.source_link_base', value: null);
        $config->set('ai-docs.redirect_guests_to', 'login');
    }

    protected function defineRoutes($router): void
    {
        Route::get('/login', fn (): string => 'login')->name('login');
    }

    #[Override]
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    /**
     * Point `<testbench>/.ai/documents` at `tests/Fixtures/documents`. Idempotent, and
     * self-healing when `composer install` wipes the skeleton.
     *
     * The fixture folders are named after the links that point at them, and sit side by
     * side, because `AiDocs::root()` resolves symlinks: a `../documents/x.md` link is
     * walked in *resolved* space, so the fixtures have to be siblings the way a real
     * `.ai/` tree is.
     */
    protected function linkFixtures(string $basePath): void
    {
        $this->link($basePath, 'documents', __DIR__.'/Fixtures/documents');
    }

    protected function link(string $basePath, string $name, string $target): void
    {
        $link = $basePath.'/.ai/'.$name;

        if (is_link($link) && readlink($link) === $target) {
            return;
        }

        if (is_link($link)) {
            unlink($link);
        }

        if (! is_dir($basePath.'/.ai')) {
            mkdir($basePath.'/.ai', recursive: true);
        }

        symlink($target, $link);
    }
}
