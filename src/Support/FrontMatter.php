<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** @see README.md — "Writing docs" */
final class FrontMatter
{
    /**
     * @return array{array<string, mixed>, string}
     */
    public static function split(string $markdown): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $markdown) ?: [];

        if (($lines[0] ?? '') !== '---') {
            return [[], $markdown];
        }

        $yaml = [];

        foreach (array_slice($lines, 1, preserve_keys: false) as $index => $line) {
            if ($line === '---') {
                return [self::parse(implode("\n", $yaml)), implode("\n", array_slice($lines, $index + 2))];
            }

            $yaml[] = $line;
        }

        return [[], $markdown];
    }

    /**
     * The markdown with these keys set in its front matter, which is added when missing.
     *
     * @param  array<string, mixed>  $values
     */
    public static function with(string $markdown, array $values): string
    {
        [$data, $body] = self::split($markdown);

        return "---\n".Yaml::dump([...$data, ...$values])."---\n".$body;
    }

    /**
     * @return array<string, mixed>
     */
    private static function parse(string $source): array
    {
        try {
            $parsed = Yaml::parse($source);
        } catch (ParseException) {
            return [];
        }

        return is_array($parsed) ? $parsed : [];
    }
}
