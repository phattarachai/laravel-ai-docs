<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use Phattarachai\AiDocs\AiDocs;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-type DocItem array{slug: string, title: string, nav: string}
 * @phpstan-type DocGroup array{key: string, label: string, items: list<DocItem>, groups: list<mixed>}
 *
 * @see README.md
 */
final class DocTree
{
    /**
     * @return list<DocGroup>
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
                'navExplicit' => $meta['navExplicit'],
                'order' => $meta['order'],
                'file' => $file->getFilename(),
            ];
        }

        return self::tree($pages);
    }

    /**
     * Every doc in the tree, depth first, in sidebar order.
     *
     * @param  list<DocGroup>|null  $groups
     * @return list<array{slug: string, title: string, nav: string, group: string}>
     */
    public static function flatten(?array $groups = null, string $trail = ''): array
    {
        $flat = [];

        foreach ($groups ?? self::groups() as $group) {
            $label = trim($trail === '' ? $group['label'] : $trail.' · '.$group['label'], ' ·');

            foreach ($group['items'] as $item) {
                $flat[] = [...$item, 'group' => $label];
            }

            /** @var list<DocGroup> $children */
            $children = $group['groups'];

            $flat = [...$flat, ...self::flatten($children, $label)];
        }

        return $flat;
    }

    public static function title(string $absolute, string $fallback): string
    {
        return Meta::read($absolute, $fallback)['title'];
    }

    /**
     * The root group comes first and carries an empty label, so its pages list ungrouped
     * above every accordion. Folders follow in path order, each nesting its own subfolders.
     *
     * @param  array<string, list<array{slug: string, title: string, nav: string, navExplicit: bool, order: int|null, file: string}>>  $pages
     * @return list<DocGroup>
     */
    private static function tree(array $pages): array
    {
        $directories = self::directories(array_keys($pages));
        $groups = [];

        if (isset($pages[''])) {
            $groups[] = [
                'key' => '_root',
                'label' => '',
                'items' => self::items($pages['']),
                'groups' => [],
            ];
        }

        foreach (self::children('', $directories) as $child) {
            $groups[] = self::branch($child, $pages, $directories);
        }

        return $groups;
    }

    /**
     * @param  array<string, list<array{slug: string, title: string, nav: string, navExplicit: bool, order: int|null, file: string}>>  $pages
     * @param  list<string>  $directories
     * @return DocGroup
     */
    private static function branch(string $directory, array $pages, array $directories): array
    {
        $name = str_contains($directory, '/') ? substr($directory, (int) mb_strrpos($directory, '/') + 1) : $directory;

        return [
            'key' => $directory,
            'label' => ucfirst(str_replace(['-', '_'], ' ', $name)),
            'items' => self::items($pages[$directory] ?? []),
            'groups' => array_map(
                fn (string $child): array => self::branch($child, $pages, $directories),
                self::children($directory, $directories),
            ),
        ];
    }

    /**
     * A folder holding only subfolders has no pages of its own, so it never reaches
     * `$pages` — walk each path back up so the intermediate levels still exist.
     *
     * @param  list<string>  $paths
     * @return list<string>
     */
    private static function directories(array $paths): array
    {
        $directories = [];

        foreach ($paths as $path) {
            $parts = $path === '' ? [] : explode('/', $path);

            for ($depth = 1; $depth <= count($parts); $depth++) {
                $directories[implode('/', array_slice($parts, 0, $depth))] = true;
            }
        }

        return array_keys($directories);
    }

    /**
     * @param  list<string>  $directories
     * @return list<string>
     */
    private static function children(string $parent, array $directories): array
    {
        $children = array_values(array_filter(
            $directories,
            function (string $directory) use ($parent): bool {
                $at = mb_strrpos($directory, '/');

                return ($at === false ? '' : mb_substr($directory, 0, $at)) === $parent;
            },
        ));

        sort($children);

        return $children;
    }

    /**
     * @param  list<array{slug: string, title: string, nav: string, navExplicit: bool, order: int|null, file: string}>  $items
     * @return list<DocItem>
     */
    private static function items(array $items): array
    {
        $items = self::disambiguate($items);

        usort($items, fn (array $a, array $b): int => [$a['order'] ?? 500, $a['file'] !== 'index.md', $a['nav']]
            <=> [$b['order'] ?? 500, $b['file'] !== 'index.md', $b['nav']]);

        return array_map(
            fn (array $item): array => [
                'slug' => $item['slug'],
                'title' => $item['title'],
                'nav' => $item['nav'],
            ],
            $items,
        );
    }

    /**
     * `Meta::shorten()` keeps the part of the title before the cut, which is the wrong half
     * for a folder whose docs all share a prefix — five `Admin panel — …` pages all list as
     * *Admin panel*. Where that happens, the tail after the cut is the half that distinguishes
     * them. Front matter always wins.
     *
     * @param  list<array{slug: string, title: string, nav: string, navExplicit: bool, order: int|null, file: string}>  $items
     * @return list<array{slug: string, title: string, nav: string, navExplicit: bool, order: int|null, file: string}>
     */
    private static function disambiguate(array $items): array
    {
        $counts = array_count_values(array_column($items, 'nav'));

        foreach ($items as $index => $item) {
            if ($item['navExplicit'] || ($counts[$item['nav']] ?? 0) < 2) {
                continue;
            }

            $items[$index]['nav'] = Meta::tail($item['title']) ?: $item['title'];
        }

        return $items;
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
