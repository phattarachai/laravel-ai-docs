<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use Illuminate\Support\Facades\Cache;
use Phattarachai\AiDocs\AiDocs;

/** @see README.md */
final class SearchIndex
{
    /**
     * @return list<array{slug: string, title: string, group: string, sections: list<array{id: string, heading: string, level: int, text: string}>}>
     */
    public static function build(): array
    {
        $docs = DocTree::flatten();

        if (config('ai-docs.cache') !== true) {
            return self::assemble($docs);
        }

        return Cache::rememberForever(
            'ai-docs:search:'.Markdown::version().':'.app()->getLocale().':'.AiDocs::panelKey()
                .':'.self::signature($docs),
            fn (): array => self::assemble($docs),
        );
    }

    /**
     * @param  list<array{slug: string, title: string, nav: string, group: string}>  $docs
     * @return list<array{slug: string, title: string, group: string, sections: list<array{id: string, heading: string, level: int, text: string}>}>
     */
    private static function assemble(array $docs): array
    {
        return array_map(fn (array $doc): array => [
            'slug' => $doc['slug'],
            'title' => $doc['title'],
            'group' => $doc['group'],
            'sections' => Docs::sections($doc['slug']),
        ], $docs);
    }

    /**
     * @param  list<array{slug: string, title: string, nav: string, group: string}>  $docs
     */
    private static function signature(array $docs): string
    {
        $parts = array_map(
            fn (array $doc): string => $doc['slug'].':'.Docs::stamp($doc['slug']),
            $docs,
        );

        return sha1(implode('|', $parts));
    }
}
