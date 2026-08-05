<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Override;
use Phattarachai\AiDocs\Console\DoctorCommand;
use Phattarachai\AiDocs\Http\Middleware\Authorize;

/** @see README.md */
final class AiDocsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-docs.php', 'ai-docs');
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'ai-docs');

        if ((bool) config('ai-docs.enabled', default: true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->registerPublishing();
            $this->commands([DoctorCommand::class]);
        }
    }

    private function registerRoutes(): void
    {
        Route::group([
            'domain' => config('ai-docs.domain'),
            'prefix' => config('ai-docs.path', 'docs'),
            'middleware' => [...(array) config('ai-docs.middleware', ['web']), Authorize::class],
            'as' => 'ai-docs.',
        ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/web.php'));
    }

    private function registerPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/ai-docs.php' => config_path('ai-docs.php'),
        ], 'ai-docs-config');

        $this->publishes([
            __DIR__.'/../lang' => lang_path('vendor/ai-docs'),
        ], 'ai-docs-lang');

        $this->publishes([
            __DIR__.'/../resources/js/pages/AiDocs.jsx' => resource_path('js/pages/AiDocs.jsx'),
        ], 'ai-docs-inertia');
    }
}
