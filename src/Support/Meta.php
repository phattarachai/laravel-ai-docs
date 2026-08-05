<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/** @see README.md */
final class Meta
{
    private const int HEAD_BYTES = 8192;

    private const array CUTS = [' — ', ' – ', ' (', ': '];

    /**
     * @return array{title: string, nav: string, order: int|null}
     */
    public static function read(string $absolute, string $fallback): array
    {
        $head = (string) @file_get_contents($absolute, length: self::HEAD_BYTES);

        [$data, $body] = self::split($head);

        $title = self::string($data, 'title') ?: (self::h1($body) ?: $fallback);
        $order = $data['order'] ?? null;

        return [
            'title' => $title,
            'nav' => self::string($data, 'nav') ?: self::shorten($title),
            'order' => is_numeric($order) ? (int) $order : null,
        ];
    }

    public static function shorten(string $title): string
    {
        foreach (self::CUTS as $cut) {
            $at = mb_strpos($title, $cut);

            if ($at !== false && $at >= 3) {
                $title = mb_substr($title, 0, $at);
            }
        }

        return (string) preg_replace('/[\s\x{2014}\x{2013}\-:(]+$/u', '', $title);
    }

    /**
     * @return array{array<string, mixed>, string}
     */
    private static function split(string $head): array
    {
        $lines = preg_split('/\R/', $head) ?: [];

        if (($lines[0] ?? '') !== '---') {
            return [[], $head];
        }

        $yaml = [];

        foreach (array_slice($lines, 1, preserve_keys: false) as $index => $line) {
            if ($line === '---') {
                return [self::yaml(implode("\n", $yaml)), implode("\n", array_slice($lines, $index + 2))];
            }

            $yaml[] = $line;
        }

        return [[], $head];
    }

    /**
     * @return array<string, mixed>
     */
    private static function yaml(string $source): array
    {
        try {
            $parsed = Yaml::parse($source);
        } catch (ParseException) {
            return [];
        }

        return is_array($parsed) ? $parsed : [];
    }

    private static function h1(string $body): string
    {
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }

            return str_starts_with($line, '# ')
                ? trim(str_replace(['`', '**', '*'], '', substr($line, 2)))
                : '';
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}
