<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Override;
use Phattarachai\AiDocs\Console\DoctorCommand;
use Phattarachai\AiDocs\Http\Middleware\Authorize;
use Phattarachai\AiDocs\Http\Middleware\SetPanel;

/** @see README.md */
final class AiDocsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ai-docs.php', 'ai-docs');

        // The current panel is process state, not request state; a new app instance
        // starts over so a test never inherits the last one's panel.
        AiDocs::usePanel(null);
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

    /**
     * One route group per panel. Longest path first, so a panel nested inside another's
     * prefix (`docs/tasks` under `docs`) is matched before the parent's catch-all
     * swallows it — Laravel resolves in registration order.
     */
    private function registerRoutes(): void
    {
        $panels = AiDocs::panels();

        uasort($panels, fn (array $a, array $b): int => mb_strlen($b['path']) <=> mb_strlen($a['path']));

        foreach ($panels as $panel) {
            Route::group([
                'domain' => config('ai-docs.domain'),
                'prefix' => $panel['path'],
                'middleware' => [
                    ...(array) config('ai-docs.middleware', ['web']),
                    SetPanel::class.':'.$panel['key'],
                    Authorize::class,
                ],
                'as' => $panel['key'] === AiDocs::DEFAULT_PANEL ? 'ai-docs.' : 'ai-docs.'.$panel['key'].'.',
            ], fn () => $this->loadRoutesFrom(__DIR__.'/../routes/web.php'));
        }
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
