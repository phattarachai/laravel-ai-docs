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
        $relative = self::under($absolute, AiDocs::root());
        $extension = mb_strtolower(pathinfo($absolute, PATHINFO_EXTENSION));

        if ($relative !== null && in_array($extension, self::MEDIA, strict: true)
            && ! AiDocs::excluded($relative) && is_file($absolute)) {
            $image->setUrl(AiDocs::url('_media/'.$relative));

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
        $relative = self::under($absolute, AiDocs::root());

        if ($relative !== null && str_ends_with($relative, '.md')
            && ! AiDocs::excluded($relative) && is_file($absolute)) {
            $link->setUrl(AiDocs::url(substr($relative, 0, -3)).($fragment === '' ? '' : '#'.$fragment));

            return;
        }

        self::outside($link, $absolute);
    }

    private static function outside(Link $link, string $absolute): void
    {
        $base = config('ai-docs.source_link_base');
        $inProject = self::under($absolute, rtrim(base_path(), '/'));

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
