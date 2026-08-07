<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs;

use Closure;
use Illuminate\Http\Request;

/**
 * @phpstan-type Panel array{key: string, path: string, root: string, label: string, exclude: list<string>}
 *
 * @see README.md
 */
final class AiDocs
{
    /**
     * The single panel an app gets when it configures `root`/`path` and no `panels`.
     * Its route names stay unprefixed, so `route('ai-docs.index')` keeps working.
     */
    public const string DEFAULT_PANEL = 'default';

    private static ?Closure $authUsing = null;

    /**
     * Which panel the current request — or the current `within()` frame — is reading.
     * Null falls back to the first configured panel.
     */
    private static ?string $panel = null;

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

    /**
     * Every configured panel, keyed and in config order — which is the order the
     * switcher shows them in.
     *
     * @return array<string, Panel>
     */
    public static function panels(): array
    {
        /** @var array<string, mixed> $configured */
        $configured = (array) config('ai-docs.panels', []);

        if ($configured === []) {
            return [self::DEFAULT_PANEL => self::normalize(self::DEFAULT_PANEL, [])];
        }

        $panels = [];

        foreach ($configured as $key => $definition) {
            $panels[(string) $key] = self::normalize((string) $key, (array) $definition);
        }

        return $panels;
    }

    public static function usePanel(?string $key): void
    {
        self::$panel = $key;
    }

    /**
     * Read something as another panel would — the Support classes all resolve against
     * "the current panel", so a cross-panel lookup is a frame rather than a parameter
     * threaded through every one of them.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function within(string $key, Closure $callback): mixed
    {
        $previous = self::$panel;
        self::$panel = $key;

        try {
            return $callback();
        } finally {
            self::$panel = $previous;
        }
    }

    /**
     * @return Panel
     */
    public static function current(): array
    {
        $panels = self::panels();

        if (self::$panel !== null && isset($panels[self::$panel])) {
            return $panels[self::$panel];
        }

        $first = reset($panels);

        return $first === false ? self::normalize(self::DEFAULT_PANEL, []) : $first;
    }

    public static function panelKey(): string
    {
        return self::current()['key'];
    }

    public static function root(): string
    {
        return self::rootOf(self::current());
    }

    /**
     * @param  Panel  $panel
     */
    public static function rootOf(array $panel): string
    {
        $path = rtrim(base_path($panel['root']), '/');

        return realpath($path) ?: $path;
    }

    public static function excluded(string $relative): bool
    {
        foreach (self::current()['exclude'] as $prefix) {
            if ($relative === $prefix || str_starts_with($relative, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function url(string $slug = ''): string
    {
        return self::urlOf(self::current(), $slug);
    }

    /**
     * @param  Panel  $panel
     */
    public static function urlOf(array $panel, string $slug = ''): string
    {
        $base = '/'.$panel['path'];

        return $slug === '' ? $base : rtrim($base, '/').'/'.$slug;
    }

    /**
     * The header switcher. Empty for a single-panel install — one tab is not a choice.
     *
     * @return list<array{key: string, label: string, url: string, current: bool}>
     */
    public static function switcher(): array
    {
        $panels = self::panels();

        if (count($panels) < 2) {
            return [];
        }

        $key = self::panelKey();

        return array_values(array_map(fn (array $panel): array => [
            'key' => $panel['key'],
            'label' => $panel['label'],
            'url' => self::urlOf($panel),
            'current' => $panel['key'] === $key,
        ], $panels));
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

    /**
     * A panel inherits `path`, `root` and `exclude` from the top-level keys, so adding
     * `panels` to an existing config only has to say what differs.
     *
     * @param  array<string, mixed>  $definition
     * @return Panel
     */
    private static function normalize(string $key, array $definition): array
    {
        $path = $definition['path'] ?? ($key === self::DEFAULT_PANEL ? config('ai-docs.path', 'docs') : $key);
        $exclude = $definition['exclude'] ?? config('ai-docs.exclude', []);

        return [
            'key' => $key,
            'path' => trim((string) $path, '/'),
            'root' => trim((string) ($definition['root'] ?? config('ai-docs.root', '.ai/documents')), '/'),
            'label' => (string) ($definition['label'] ?? ucfirst(str_replace(['-', '_'], ' ', $key))),
            'exclude' => array_values(array_map(
                fn (mixed $prefix): string => trim((string) $prefix, '/'),
                (array) $exclude,
            )),
        ];
    }
}
