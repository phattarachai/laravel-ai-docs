<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

/** @see README.md */
final class Slug
{
    /**
     * @param  array<string, int>  $seen  running count per slug, for the `-1` / `-2` suffixes
     */
    public static function make(string $text, array &$seen = []): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = preg_replace('/[^\p{L}\p{N}\p{M} _-]+/u', '', $slug) ?? '';
        $slug = str_replace(' ', '-', $slug);

        if ($slug === '') {
            $slug = 'section';
        }

        $count = $seen[$slug] ?? 0;
        $seen[$slug] = $count + 1;

        return $count === 0 ? $slug : $slug.'-'.$count;
    }
}
