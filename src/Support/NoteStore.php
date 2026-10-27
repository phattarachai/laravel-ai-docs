<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Phattarachai\AiDocs\AiDocs;
use Symfony\Component\Finder\Finder;

/**
 * Pushed notes on the server: plain files under a note panel's root, so the tree,
 * search, media route and render cache read them as they read any repo folder.
 *
 * @phpstan-import-type Panel from AiDocs
 *
 * @see README.md — "Notes"
 */
final class NoteStore
{
    /**
     * Write the files (validated {@see NotePath::file()} paths, relative to the panel
     * root) and stamp every page with the push time and author.
     *
     * @param  Panel  $panel
     * @param  array<string, string>  $files
     * @return list<string> the pages written, relative to the panel root
     */
    public static function put(array $panel, array $files, ?string $author, ?string $prune): array
    {
        $root = AiDocs::rootOf($panel);

        if ($prune !== null) {
            File::deleteDirectory("{$root}/{$prune}");
        }

        $pages = [];

        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname("{$root}/{$path}"));
            File::put("{$root}/{$path}", str_ends_with($path, '.md') ? self::stamp($contents, $author) : $contents);

            if (str_ends_with($path, '.md')) {
                $pages[] = $path;
            }
        }

        return $pages;
    }

    /**
     * The note at `$path.md` with the images it shows, and everything under a folder
     * at `$path`, keyed by path relative to the panel root.
     *
     * @param  Panel  $panel
     * @return array<string, string>
     */
    public static function get(array $panel, string $path): array
    {
        $root = AiDocs::rootOf($panel);
        $files = [];

        if (is_file("{$root}/{$path}.md")) {
            $files["{$path}.md"] = (string) file_get_contents("{$root}/{$path}.md");
            $files = [...$files, ...self::images($root, $path)];
        }

        foreach (is_dir("{$root}/{$path}") ? self::folder("{$root}/{$path}") : [] as $relative) {
            $files["{$path}/{$relative}"] = (string) file_get_contents("{$root}/{$path}/{$relative}");
        }

        return $files;
    }

    /**
     * @param  Panel  $panel
     */
    public static function delete(array $panel, string $path): bool
    {
        $root = AiDocs::rootOf($panel);
        $page = File::delete("{$root}/{$path}.md");
        $folder = File::deleteDirectory("{$root}/{$path}");

        self::dropEmptyParents($root, dirname($path));

        return $page || $folder;
    }

    private static function stamp(string $markdown, ?string $author): string
    {
        return FrontMatter::with($markdown, array_filter([
            'updated' => Date::now()->toIso8601String(),
            'author' => $author,
        ], fn (?string $value): bool => $value !== null && $value !== ''));
    }

    /**
     * @return array<string, string>
     */
    private static function images(string $root, string $path): array
    {
        $images = [];
        $dir = str_contains($path, '/') ? dirname($path) : '';

        foreach (NoteImages::of((string) file_get_contents("{$root}/{$path}.md")) as $reference) {
            $image = NotePath::join($dir, $reference);

            if ($image !== null && NotePath::file($image) !== null && is_file("{$root}/{$image}")) {
                $images[$image] = (string) file_get_contents("{$root}/{$image}");
            }
        }

        return $images;
    }

    /**
     * @return list<string>
     */
    private static function folder(string $absolute): array
    {
        $files = [];

        foreach (Finder::create()->files()->in($absolute)->ignoreDotFiles(true)->sortByName() as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if (NotePath::file($relative) !== null) {
                $files[] = $relative;
            }
        }

        return $files;
    }

    private static function dropEmptyParents(string $root, string $dir): void
    {
        while ($dir !== '.' && $dir !== '' && File::isDirectory("{$root}/{$dir}") && File::isEmptyDirectory("{$root}/{$dir}")) {
            File::deleteDirectory("{$root}/{$dir}");
            $dir = dirname($dir);
        }
    }
}
