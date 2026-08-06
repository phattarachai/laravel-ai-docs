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
     * @return array{title: string, nav: string, navExplicit: bool, order: int|null}
     */
    public static function read(string $absolute, string $fallback): array
    {
        $head = (string) @file_get_contents($absolute, length: self::HEAD_BYTES);

        [$data, $body] = self::split($head);

        $title = self::string($data, 'title') ?: (self::h1($body) ?: $fallback);
        $nav = self::string($data, 'nav');
        $order = $data['order'] ?? null;

        return [
            'title' => $title,
            'nav' => $nav ?: self::shorten($title),
            'navExplicit' => $nav !== '',
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
     * @see docs/authoring.md — "Ordering and labels"
     */
    public static function tail(string $title): string
    {
        $at = null;
        $cut = '';

        foreach (self::CUTS as $candidate) {
            $index = mb_strpos($title, $candidate);

            if ($index !== false && $index >= 3 && ($at === null || $index < $at)) {
                $at = $index;
                $cut = $candidate;
            }
        }

        if ($at === null) {
            return '';
        }

        $tail = trim(mb_substr($title, $at + mb_strlen($cut)));

        if ($cut === ' (' && str_ends_with($tail, ')')) {
            $tail = rtrim(mb_substr($tail, 0, -1));
        }

        return $tail;
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
