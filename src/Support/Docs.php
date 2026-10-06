<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use Illuminate\Support\Facades\Cache;
use Phattarachai\AiDocs\AiDocs;

/** @see README.md */
final class Docs
{
    /**
     * @return array{slug: string, title: string, path: string, html: string, toc: list<array{id: string, text: string, level: int}>}|null
     */
    public static function page(string $slug): ?array
    {
        $relative = self::resolve(trim($slug, '/'));

        if ($relative === null) {
            return null;
        }

        $rendered = self::rendered($relative);
        $absolute = AiDocs::root().'/'.$relative;

        return [
            'slug' => substr($relative, 0, -3),
            'title' => DocTree::title($absolute, basename($relative)),
            'path' => AiDocs::current()['root'].'/'.$relative,
            'html' => $rendered['html'],
            'toc' => $rendered['toc'],
        ];
    }

    /**
     * @return list<array{id: string, heading: string, level: int, text: string}>
     */
    public static function sections(string $slug): array
    {
        $relative = self::resolve(trim($slug, '/'));

        return $relative === null ? [] : self::rendered($relative)['sections'];
    }

    /**
     * The mtime of the page and of every SVG it inlines — what the search index keys on.
     */
    public static function stamp(string $slug): string
    {
        $relative = self::resolve(trim($slug, '/'));

        if ($relative === null) {
            return '';
        }

        $files = [AiDocs::root().'/'.$relative => 0, ...self::rendered($relative)['files']];

        return implode(',', array_map(fn (string $file): int => (int) @filemtime($file), array_keys($files)));
    }

    public static function first(): ?string
    {
        if (self::resolve('index') !== null) {
            return 'index';
        }

        return DocTree::flatten()[0]['slug'] ?? null;
    }

    public static function media(string $path): ?string
    {
        $extensions = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif'];
        $path = trim($path, '/');

        if ($path === '' || preg_match('#^[\p{L}\p{N}/_.-]+$#u', $path) !== 1) {
            return null;
        }

        if (! in_array(mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, strict: true)) {
            return null;
        }

        $relative = self::within(realpath(AiDocs::root().'/'.$path), realpath(AiDocs::root()));

        if ($relative === null || AiDocs::excluded($relative)) {
            return null;
        }

        return str_replace('\\', '/', (string) realpath(AiDocs::root().'/'.$path));
    }

    private static function resolve(string $slug): ?string
    {
        if ($slug === '' || preg_match('#^[\p{L}\p{N}/_.-]+$#u', $slug) !== 1) {
            return null;
        }

        $relative = self::within(realpath(AiDocs::root().'/'.$slug.'.md'), realpath(AiDocs::root()));

        return $relative === null || AiDocs::excluded($relative) ? null : $relative;
    }

    /**
     * The path of $absolute relative to $root, or null if it is false or escapes the
     * root. Separators are normalised to `/` first, so a Windows `realpath()` (which
     * hands back backslashes) still matches a forward-slash root — without this every
     * page resolved to null and the docs served blank on a Windows/Laragon host.
     */
    private static function within(string|false $absolute, string|false $root): ?string
    {
        if ($absolute === false || $root === false) {
            return null;
        }

        $absolute = str_replace('\\', '/', $absolute);
        $root = str_replace('\\', '/', $root);

        return str_starts_with($absolute, $root.'/') ? substr($absolute, strlen($root) + 1) : null;
    }

    /**
     * The render cache is keyed on the markdown file; an inlined SVG is a second input
     * that the key cannot name before the page is parsed. So each entry records every SVG
     * it inlined with that file's mtime, and is re-rendered when any of them has moved
     * on. Otherwise, editing only the drawing would never reach the page.
     *
     * @return array{html: string, toc: list<array{id: string, text: string, level: int}>, sections: list<array{id: string, heading: string, level: int, text: string}>, files: array<string, int>}
     */
    private static function rendered(string $relative): array
    {
        $absolute = AiDocs::root().'/'.$relative;
        $markdown = (string) file_get_contents($absolute);
        $directory = str_contains($relative, '/') ? dirname($relative) : '';

        if (config('ai-docs.cache') !== true) {
            return Markdown::render($markdown, $directory);
        }

        $key = implode(':', [
            'ai-docs',
            Markdown::version(),
            app()->getLocale(),
            AiDocs::panelKey(),
            sha1($relative),
            (int) filemtime($absolute),
        ]);

        $cached = Cache::get($key);

        if (is_array($cached) && is_array($cached['files'] ?? null) && self::fresh($cached['files'])) {
            /** @var array{html: string, toc: list<array{id: string, text: string, level: int}>, sections: list<array{id: string, heading: string, level: int, text: string}>, files: array<string, int>} $cached */
            return $cached;
        }

        $rendered = Markdown::render($markdown, $directory);
        Cache::forever($key, $rendered);

        return $rendered;
    }

    /**
     * @param  array<array-key, mixed>  $files
     */
    private static function fresh(array $files): bool
    {
        foreach ($files as $file => $mtime) {
            if ((int) @filemtime((string) $file) !== $mtime) {
                return false;
            }
        }

        return true;
    }
}
