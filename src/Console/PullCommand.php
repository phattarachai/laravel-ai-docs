<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\NotePath;
use Phattarachai\AiDocs\Support\NotesRemote;
use RuntimeException;

/** @see README.md — "Notes" */
final class PullCommand extends Command
{
    protected $signature = 'ai-docs:pull
        {note? : the note or category path, or the page URL; none lists the notes}
        {--panel= : the note panel (default: the one in the URL, else the first)}
        {--to= : the folder to write into (default: the panel\'s working copy, e.g. .ai/notes)}';

    protected $description = 'Pull a note, or a category of them, from a note panel into the working copy';

    public function handle(NotesRemote $remote): int
    {
        try {
            return $this->argument('note') === null ? $this->list($remote) : $this->pull($remote);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());
        } catch (RequestException $exception) {
            $this->components->error(NotesRemote::reason($exception));
        }

        return self::FAILURE;
    }

    private function list(NotesRemote $remote): int
    {
        $result = $remote->list($this->option('panel') ?: null);

        $this->table(['path', 'title', 'updated', 'author'], array_map(
            fn (array $note): array => [$note['slug'], $note['title'], $note['updated'] ?? '', $note['author'] ?? ''],
            $result['notes'],
        ));

        return self::SUCCESS;
    }

    private function pull(NotesRemote $remote): int
    {
        [$panel, $path] = $this->target((string) $this->argument('note'));
        $folder = $this->folder($panel);

        foreach ($remote->pull($path, $panel) as $file => $contents) {
            File::ensureDirectoryExists(dirname("{$folder}/{$file}"));
            File::put("{$folder}/{$file}", $contents);
            $this->components->twoColumnDetail($file, "{$folder}/{$file}");
        }

        return self::SUCCESS;
    }

    /**
     * @return array{string|null, string}
     */
    private function target(string $note): array
    {
        [$panel, $path] = str_contains($note, '://') ? $this->fromUrl($note) : [null, $note];
        $normalized = NotePath::note($path);

        throw_if($normalized === null, RuntimeException::class, "{$path} is not a note path.");

        return [$this->option('panel') ?: $panel, $normalized];
    }

    /**
     * The panel and path in a page URL: `https://app.test/docs/notes/flows/cart#step-2`.
     *
     * @return array{string|null, string}
     */
    private function fromUrl(string $url): array
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $panels = AiDocs::notePanels();

        uasort($panels, fn (array $a, array $b): int => mb_strlen($b['path']) <=> mb_strlen($a['path']));

        foreach ($panels as $panel) {
            $at = mb_strpos($path.'/', '/'.$panel['path'].'/');

            if ($at !== false) {
                return [$panel['key'], trim(mb_substr($path, $at + mb_strlen($panel['path']) + 1), '/')];
            }
        }

        throw new RuntimeException("{$url} is not under any note panel configured here.");
    }

    private function folder(?string $key): string
    {
        if ($this->option('to')) {
            return rtrim((string) $this->option('to'), '/');
        }

        $panel = AiDocs::notePanel($key);

        throw_if($panel === null, RuntimeException::class, 'No note panel is configured here: pass --to.');

        $this->ignore($panel['local']);

        return base_path($panel['local']);
    }

    /**
     * The working copy is a scratch pad, never committed: a note worth keeping moves
     * into the docs tree.
     */
    private function ignore(string $local): void
    {
        $gitignore = base_path('.gitignore');
        $lines = File::exists($gitignore) ? array_map(trim(...), File::lines($gitignore)->all()) : [];

        if (array_intersect(["/{$local}/", "{$local}/", "/{$local}", $local], $lines) !== []) {
            return;
        }

        File::append($gitignore, (File::exists($gitignore) && ! str_ends_with(File::get($gitignore), "\n") ? "\n" : '')."/{$local}/\n");
        $this->components->info("Added /{$local}/ to .gitignore.");
    }
}
