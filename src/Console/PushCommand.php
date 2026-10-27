<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Console;

use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;
use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\FrontMatter;
use Phattarachai\AiDocs\Support\NoteImages;
use Phattarachai\AiDocs\Support\NotePath;
use Phattarachai\AiDocs\Support\NotesRemote;
use RuntimeException;
use Symfony\Component\Finder\Finder;

/** @see README.md — "Notes" */
final class PushCommand extends Command
{
    protected $signature = 'ai-docs:push
        {source : a markdown file or a folder; with --delete, the note\'s path}
        {--path= : where it goes in the panel, e.g. flows/cart/price-recalc (default: its place in the working copy, else its name)}
        {--title= : the page title, written into a single file\'s front matter}
        {--panel= : the note panel (default: the working copy it sits in, else the first)}
        {--author= : shown beside the page\'s date (default: ai-docs.remote.author)}
        {--prune : with a folder, also remove the files on the server the folder no longer has}
        {--delete : remove the note (and the folder) at that path instead}
        {--dry-run : list what would be sent without sending it}';

    protected $description = 'Push a markdown note, or a folder of them, to a note panel on the running app';

    public function handle(NotesRemote $remote): int
    {
        try {
            return $this->option('delete') ? $this->delete($remote) : $this->push($remote);
        } catch (RuntimeException $exception) {
            $this->components->error($exception->getMessage());
        } catch (RequestException $exception) {
            $this->components->error(NotesRemote::reason($exception));
        }

        return self::FAILURE;
    }

    private function push(NotesRemote $remote): int
    {
        $source = rtrim((string) $this->argument('source'), '/');

        throw_unless(File::exists($source), RuntimeException::class, "{$source} does not exist.");

        [$panel, $path] = $this->target($source);
        $files = File::isDirectory($source) ? $this->folder($source, $path) : $this->page($source, $path);

        foreach (array_keys($files) as $file) {
            $this->components->twoColumnDetail($file, ($panel ?? 'first note panel'));
        }

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $prune = $this->option('prune') && File::isDirectory($source) ? $path : null;
        $result = $remote->push($files, $panel, $this->author(), $prune);

        foreach ($result['pages'] as $page) {
            $this->components->info("{$page['slug']} → {$page['url']}");
        }

        return self::SUCCESS;
    }

    private function delete(NotesRemote $remote): int
    {
        $path = NotePath::note((string) $this->argument('source'));

        throw_if($path === null, RuntimeException::class, 'Give the note\'s path in the panel, e.g. flows/cart/price-recalc.');

        $remote->delete($path, $this->option('panel') ?: null);
        $this->components->info("Deleted {$path}.");

        return self::SUCCESS;
    }

    /**
     * The panel and note path: --panel and --path when given, else where the source
     * sits in a note panel's working copy, else the first panel and the source's name.
     *
     * @return array{string|null, string}
     */
    private function target(string $source): array
    {
        [$panel, $relative] = $this->workingCopy($source) ?? [null, preg_replace('/\.md$/', '', basename($source))];
        $path = (string) ($this->option('path') ?: $relative);

        throw_if(
            NotePath::note($path) === null && ! ($path === '' && File::isDirectory($source)),
            RuntimeException::class,
            "{$path} is not a note path: letters, digits, _ . and - in /-separated segments.",
        );

        return [$this->option('panel') ?: $panel, NotePath::note($path) ?? ''];
    }

    /**
     * @return array{string, string}|null
     */
    private function workingCopy(string $source): ?array
    {
        $absolute = str_replace('\\', '/', (string) realpath($source));

        foreach (AiDocs::notePanels() as $panel) {
            $local = str_replace('\\', '/', (string) realpath(base_path($panel['local'])));

            if ($local !== '' && ($absolute === $local || str_starts_with($absolute, $local.'/'))) {
                return [$panel['key'], (string) preg_replace('/\.md$/', '', ltrim(substr($absolute, strlen($local)), '/'))];
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function page(string $source, string $path): array
    {
        throw_unless(str_ends_with($source, '.md'), RuntimeException::class, "{$source} is not a markdown file.");

        $markdown = File::get($source);

        if ($this->option('title')) {
            $markdown = FrontMatter::with($markdown, ['title' => (string) $this->option('title')]);
        }

        return ["{$path}.md" => $markdown, ...$this->images($markdown, dirname($source), $path)];
    }

    /**
     * @return array<string, string>
     */
    private function images(string $markdown, string $sourceDir, string $path): array
    {
        $images = [];
        $dir = str_contains($path, '/') ? dirname($path) : '';

        foreach (NoteImages::of($markdown) as $reference) {
            $target = NotePath::join($dir, $reference);

            throw_if($target === null || NotePath::file($target) === null, RuntimeException::class, "The image {$reference} would land outside the panel.");
            throw_unless(File::isFile("{$sourceDir}/{$reference}"), RuntimeException::class, "The image {$reference} is not beside the page.");

            $images[$target] = File::get("{$sourceDir}/{$reference}");
        }

        return $images;
    }

    /**
     * @return array<string, string>
     */
    private function folder(string $source, string $path): array
    {
        $files = [];

        foreach (Finder::create()->files()->in($source)->ignoreDotFiles(true)->sortByName() as $file) {
            $target = ltrim($path.'/'.str_replace('\\', '/', $file->getRelativePathname()), '/');

            if (NotePath::file($target) !== null) {
                $files[$target] = $file->getContents();
            }
        }

        throw_if($files === [], RuntimeException::class, "{$source} has no markdown or images to push.");

        return $files;
    }

    private function author(): ?string
    {
        $author = $this->option('author') ?: config('ai-docs.remote.author');

        return is_string($author) && $author !== '' ? $author : null;
    }
}
