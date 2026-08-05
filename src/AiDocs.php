<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs;

use Closure;
use Illuminate\Http\Request;

/** @see README.md */
final class AiDocs
{
    private static ?Closure $authUsing = null;

    public static function auth(Closure $callback): void
    {
        self::$authUsing = $callback;
    }

    public static function flushAuth(): void
    {
        self::$authUsing = null;
    }

    public static function hasGate(): bool
    {
        return self::$authUsing instanceof Closure;
    }

    public static function check(Request $request): bool
    {
        return self::$authUsing instanceof Closure && (self::$authUsing)($request);
    }

    public static function root(): string
    {
        $path = rtrim(base_path((string) config('ai-docs.root')), '/');

        return realpath($path) ?: $path;
    }

    public static function excluded(string $relative): bool
    {
        foreach ((array) config('ai-docs.exclude', []) as $prefix) {
            $prefix = trim((string) $prefix, '/');

            if ($relative === $prefix || str_starts_with($relative, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function url(string $slug = ''): string
    {
        $base = '/'.trim((string) config('ai-docs.path', 'docs'), '/');

        return $slug === '' ? $base : $base.'/'.$slug;
    }

    /**
     * @return array{name: string, accent: string, url: string, logo: string|null, tables: string}
     */
    public static function brand(): array
    {
        /** @var array<string, mixed> $brand */
        $brand = (array) config('ai-docs.brand', []);

        return [
            'name' => (string) ($brand['name'] ?? 'Docs'),
            'accent' => (string) ($brand['accent'] ?? '#3b82f6'),
            'url' => (string) ($brand['url'] ?? '/'),
            'logo' => $brand['logo'] === null ? null : (string) $brand['logo'],
            'tables' => (string) config('ai-docs.tables', 'wrap'),
        ];
    }
}
