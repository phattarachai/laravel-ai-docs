<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use Phattarachai\AiDocs\AiDocs;

/** @see README.md */
final class Links
{
    private const array MEDIA = ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif'];

    public static function image(Image $image, string $docDir): void
    {
        $url = $image->getUrl();

        if ($url === '' || preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $url) === 1) {
            return;
        }

        $absolute = self::normalize(AiDocs::root().'/'.ltrim($docDir.'/'.explode('#', $url)[0], '/'));
        $media = self::media($absolute);

        if ($media !== null) {
            $image->setUrl($media);

            return;
        }

        if (! $image->firstChild() instanceof Node) {
            $image->appendChild(new Text($url));
        }

        $image->data->set('doc_outside', value: true);
    }

    public static function rewrite(Link $link, string $docDir): void
    {
        $url = $link->getUrl();

        if ($url === '' || str_starts_with($url, '#')) {
            return;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:|^//#i', $url) === 1) {
            self::attributes($link, ['target' => '_blank', 'rel' => 'noreferrer noopener']);

            return;
        }

        [$path, $fragment] = array_pad(explode('#', $url, 2), 2, '');

        $absolute = self::normalize(AiDocs::root().'/'.ltrim($docDir.'/'.$path, '/'));
        $target = self::target($absolute);

        if ($target !== null) {
            $link->setUrl($target.($fragment === '' ? '' : '#'.$fragment));

            return;
        }

        self::outside($link, $absolute);
    }

    /**
     * The `_media` URL of an in-tree image, served by whichever panel holds it.
     */
    private static function media(string $absolute): ?string
    {
        if (! in_array(mb_strtolower(pathinfo($absolute, PATHINFO_EXTENSION)), self::MEDIA, strict: true)
            || ! is_file($absolute)) {
            return null;
        }

        foreach (self::candidates() as $panel) {
            $relative = self::under($absolute, AiDocs::rootOf($panel));

            if ($relative === null || AiDocs::within($panel['key'], fn (): bool => AiDocs::excluded($relative))) {
                continue;
            }

            return AiDocs::urlOf($panel, '_media/'.$relative);
        }

        return null;
    }

    /**
     * Where a relative link lands, or null if it leaves the panels entirely. Every panel
     * is a candidate, not just the one being read — splitting the docs across panels must
     * not turn a doc-to-doc link into a link off to the code host.
     */
    private static function target(string $absolute): ?string
    {
        foreach (self::candidates() as $panel) {
            $relative = self::under($absolute, AiDocs::rootOf($panel));

            if ($relative === null) {
                continue;
            }

            $url = AiDocs::within($panel['key'], fn (): ?string => self::resolve($absolute, $relative));

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    private static function resolve(string $absolute, string $relative): ?string
    {
        if (AiDocs::excluded($relative)) {
            return null;
        }

        if (str_ends_with($relative, '.md')) {
            return is_file($absolute) ? AiDocs::url(substr($relative, 0, -3)) : null;
        }

        // A *link* to a screenshot, not an embed of it. Serving it through `_media` keeps
        // the reader in the panel instead of bouncing them to the code host.
        if (in_array(mb_strtolower(pathinfo($relative, PATHINFO_EXTENSION)), self::MEDIA, strict: true)) {
            return is_file($absolute) ? AiDocs::url('_media/'.$relative) : null;
        }

        $slug = is_dir($absolute) ? DocTree::landing($relative) : null;

        return $slug === null ? null : AiDocs::url($slug);
    }

    /**
     * The panel being read first, then the deepest roots — so a panel rooted at
     * `.ai/tasks` claims the link before one rooted at `.ai`.
     *
     * @return list<array{key: string, path: string, root: string, label: string, exclude: list<string>}>
     */
    private static function candidates(): array
    {
        $panels = AiDocs::panels();
        $current = AiDocs::panelKey();

        uasort($panels, fn (array $a, array $b): int => [$b['key'] === $current, mb_strlen($b['root'])]
            <=> [$a['key'] === $current, mb_strlen($a['root'])]);

        return array_values($panels);
    }

    private static function outside(Link $link, string $absolute): void
    {
        $base = config('ai-docs.source_link_base');
        $inProject = self::under($absolute, str_replace('\\', '/', rtrim(base_path(), '/')));

        if (is_string($base) && $base !== '' && $inProject !== null) {
            $link->setUrl(rtrim($base, '/').'/'.$inProject);
            self::attributes($link, ['target' => '_blank', 'rel' => 'noreferrer noopener']);

            return;
        }

        $link->data->set('doc_outside', value: true);
    }

    /**
     * @param  array<string, string>  $add
     */
    private static function attributes(Link $link, array $add): void
    {
        $link->data->set('attributes', $add + (array) $link->data->get('attributes'));
    }

    private static function under(string $absolute, string $root): ?string
    {
        if ($absolute === $root) {
            return '';
        }

        return str_starts_with($absolute, $root.'/') ? substr($absolute, strlen($root) + 1) : null;
    }

    private static function normalize(string $path): string
    {
        $out = [];

        foreach (explode('/', $path) as $segment) {
            match (true) {
                $segment === '' || $segment === '.' => null,
                $segment === '..' => array_pop($out),
                default => $out[] = $segment,
            };
        }

        return '/'.implode('/', $out);
    }
}
