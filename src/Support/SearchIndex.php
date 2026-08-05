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
        $groups = DocTree::groups();

        if (config('ai-docs.cache') !== true) {
            return self::assemble($groups);
        }

        return Cache::rememberForever(
            'ai-docs:search:'.Markdown::version().':'.app()->getLocale().':'.self::signature($groups),
            fn (): array => self::assemble($groups),
        );
    }

    /**
     * @param  list<array{key: string, label: string, items: list<array{slug: string, title: string}>}>  $groups
     * @return list<array{slug: string, title: string, group: string, sections: list<array{id: string, heading: string, level: int, text: string}>}>
     */
    private static function assemble(array $groups): array
    {
        $docs = [];

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $docs[] = [
                    'slug' => $item['slug'],
                    'title' => $item['title'],
                    'group' => $group['label'],
                    'sections' => Docs::sections($item['slug']),
                ];
            }
        }

        return $docs;
    }

    /**
     * @param  list<array{key: string, label: string, items: list<array{slug: string, title: string}>}>  $groups
     */
    private static function signature(array $groups): string
    {
        $parts = [];

        foreach ($groups as $group) {
            foreach ($group['items'] as $item) {
                $parts[] = $item['slug'].':'.(int) @filemtime(AiDocs::root().'/'.$item['slug'].'.md');
            }
        }

        return sha1(implode('|', $parts));
    }
}
