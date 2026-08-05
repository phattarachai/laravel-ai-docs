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
            'path' => trim((string) config('ai-docs.root'), '/').'/'.$relative,
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

    public static function first(): ?string
    {
        if (self::resolve('index') !== null) {
            return 'index';
        }

        $groups = DocTree::groups();

        return $groups[0]['items'][0]['slug'] ?? null;
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

        $absolute = realpath(AiDocs::root().'/'.$path);
        $root = realpath(AiDocs::root());

        if ($absolute === false || $root === false || ! str_starts_with($absolute, $root.'/')) {
            return null;
        }

        return AiDocs::excluded(substr($absolute, strlen($root) + 1)) ? null : $absolute;
    }

    private static function resolve(string $slug): ?string
    {
        if ($slug === '' || preg_match('#^[\p{L}\p{N}/_.-]+$#u', $slug) !== 1) {
            return null;
        }

        $absolute = realpath(AiDocs::root().'/'.$slug.'.md');
        $root = realpath(AiDocs::root());

        if ($absolute === false || $root === false || ! str_starts_with($absolute, $root.'/')) {
            return null;
        }

        $relative = substr($absolute, strlen($root) + 1);

        return AiDocs::excluded($relative) ? null : $relative;
    }

    /**
     * @return array{html: string, toc: list<array{id: string, text: string, level: int}>, sections: list<array{id: string, heading: string, level: int, text: string}>}
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
            sha1($relative),
            (int) filemtime($absolute),
        ]);

        return Cache::rememberForever($key, fn (): array => Markdown::render($markdown, $directory));
    }
}
