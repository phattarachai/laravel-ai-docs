<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\DocTree;
use Throwable;

/** @see README.md — "Install" */
final class DoctorCommand extends Command
{
    protected $signature = 'ai-docs:doctor';

    protected $description = 'Check that this application satisfies the AI Docs requirements.';

    public function handle(): int
    {
        $failures = 0;

        $failures += $this->check('Panel enabled', fn (): ?string => config('ai-docs.enabled')
            ? null
            : 'ai-docs.enabled is false, so no routes are registered.');

        $failures += $this->check('Routes registered', fn (): ?string => Route::has('ai-docs.index')
            ? null
            : 'Route [ai-docs.index] is missing.');

        $failures += $this->check('Access gate', fn (): ?string => AiDocs::hasGate()
            ? null
            : 'No AiDocs::auth() callback is registered, so every request is refused.');

        $failures += $this->check('Docs root', fn (): ?string => $this->root());

        $failures += $this->check('Inertia page published', fn (): ?string => file_exists(resource_path('js/pages/AiDocs.jsx'))
            ? null
            : 'Run `php artisan vendor:publish --tag=ai-docs-inertia`.');

        $failures += $this->check('Vite alias @ai-docs', fn (): ?string => $this->fileContains(base_path('vite.config.js'), '@ai-docs')
            ? null
            : 'Add a resolve.alias entry for @ai-docs — see the package README.');

        $failures += $this->check('mermaid installed', fn (): ?string => $this->fileContains(base_path('package.json'), '"mermaid"')
            ? null
            : 'Run `npm install mermaid` — diagram fences fall back to plain text without it.');

        $this->newLine();

        if ($failures === 0) {
            $this->components->info('AI Docs looks correctly installed.');

            return self::SUCCESS;
        }

        $this->components->error("{$failures} check(s) need attention.");

        return self::FAILURE;
    }

    /**
     * @param  callable(): (string|null)  $check
     */
    private function check(string $label, callable $check): int
    {
        try {
            $problem = $check();
        } catch (Throwable $throwable) {
            $problem = $throwable->getMessage();
        }

        $this->components->twoColumnDetail($label, $problem === null ? '<fg=green>OK</>' : '<fg=red>FAIL</>');

        if ($problem !== null) {
            $this->line("  <fg=gray>{$problem}</>");

            return 1;
        }

        return 0;
    }

    private function root(): ?string
    {
        $root = AiDocs::root();

        if (! is_dir($root)) {
            return "{$root} does not exist — set ai-docs.root to your markdown folder.";
        }

        $pages = array_sum(array_map(fn (array $group): int => count($group['items']), DocTree::groups()));

        if ($pages === 0) {
            return "{$root} holds no servable .md file.";
        }

        $this->line("  <fg=gray>{$root} · {$pages} pages</>");

        return null;
    }

    private function fileContains(string $path, string $needle): bool
    {
        return file_exists($path) && str_contains((string) file_get_contents($path), $needle);
    }
}
