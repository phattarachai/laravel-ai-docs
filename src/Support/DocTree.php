<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use Phattarachai\AiDocs\AiDocs;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/** @see README.md */
final class DocTree
{
    /**
     * @return list<array{key: string, label: string, items: list<array{slug: string, title: string, nav: string}>}>
     */
    public static function groups(): array
    {
        $root = AiDocs::root();

        if (! is_dir($root)) {
            return [];
        }

        $pages = [];

        foreach (self::finder($root) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());

            if (AiDocs::excluded($relative)) {
                continue;
            }

            $directory = str_replace('\\', '/', $file->getRelativePath());
            $meta = Meta::read($file->getPathname(), $file->getFilename());

            $pages[$directory][] = [
                'slug' => substr($relative, 0, -3),
                'title' => $meta['title'],
                'nav' => $meta['nav'],
                'order' => $meta['order'],
                'file' => $file->getFilename(),
            ];
        }

        return self::order($pages);
    }

    public static function title(string $absolute, string $fallback): string
    {
        return Meta::read($absolute, $fallback)['title'];
    }

    /**
     * @param  array<string, list<array{slug: string, title: string, nav: string, order: int|null, file: string}>>  $pages
     * @return list<array{key: string, label: string, items: list<array{slug: string, title: string, nav: string}>}>
     */
    private static function order(array $pages): array
    {
        uksort($pages, fn (string $a, string $b): int => [$a === '' ? 0 : 1, $a] <=> [$b === '' ? 0 : 1, $b]);

        $groups = [];

        foreach ($pages as $directory => $items) {
            usort($items, fn (array $a, array $b): int => [$a['order'] ?? 500, $a['file'] !== 'index.md', $a['nav']]
                <=> [$b['order'] ?? 500, $b['file'] !== 'index.md', $b['nav']]);

            $groups[] = [
                'key' => $directory === '' ? '_root' : $directory,
                'label' => $directory === '' ? '' : ucfirst(str_replace(['-', '_'], ' ', $directory)),
                'items' => array_map(
                    fn (array $item): array => [
                        'slug' => $item['slug'],
                        'title' => $item['title'],
                        'nav' => $item['nav'],
                    ],
                    $items,
                ),
            ];
        }

        return $groups;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private static function finder(string $root): iterable
    {
        return Finder::create()
            ->files()
            ->in($root)
            ->exclude(array_map(fn (string $p): string => trim($p, '/'), (array) config('ai-docs.exclude', [])))
            ->name('*.md')
            ->sortByName();
    }
}
