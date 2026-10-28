<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs;

use Closure;
use Illuminate\Http\Request;

/**
 * @phpstan-type Panel array{key: string, path: string, root: string, label: string, exclude: list<string>, notes: bool, local: string}
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

    /**
     * The header's theme picker, in menu order. Keep in sync with `SCHEMES` in
     * resources/js/ai-docs/scheme.js.
     */
    public const array THEMES = ['light', 'soft', 'nord', 'dark'];

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

        return self::excludeWorkingCopies($panels);
    }

    /**
     * The panels Claude pushes notes to, keyed and in config order.
     *
     * @return array<string, Panel>
     */
    public static function notePanels(): array
    {
        return array_filter(self::panels(), fn (array $panel): bool => $panel['notes']);
    }

    /**
     * A note panel by key, or the first one when the key is null.
     *
     * @return Panel|null
     */
    public static function notePanel(?string $key): ?array
    {
        $panels = self::notePanels();

        return $key === null ? (reset($panels) ?: null) : ($panels[$key] ?? null);
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

        // Forward slashes always — a Windows realpath() hands back backslashes, which
        // break every downstream `str_starts_with($absolute, $root.'/')` boundary check.
        return str_replace('\\', '/', realpath($path) ?: $path);
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
     * A root-relative path — a pathname, never a full URL, because the React client
     * treats it as one (active-link matching against `location.pathname`, Inertia
     * visits). Resolved through Laravel's `url()` so a subfolder deploy (the app
     * served from `/myapp/public`, say) is folded into the path; at the domain root
     * this is still just `/docs`.
     *
     * @param  Panel  $panel
     */
    public static function urlOf(array $panel, string $slug = ''): string
    {
        $path = $slug === '' ? $panel['path'] : $panel['path'].'/'.$slug;

        return parse_url(url($path), PHP_URL_PATH) ?: '/'.ltrim($path, '/');
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
     * `theme` is the default for a reader who hasn't picked one; null follows their OS.
     *
     * @return array{name: string, accent: string, url: string, logo: string|null, tables: string, theme: string|null}
     */
    public static function brand(): array
    {
        /** @var array<string, mixed> $brand */
        $brand = (array) config('ai-docs.brand', []);
        $theme = config('ai-docs.theme');

        return [
            'name' => (string) ($brand['name'] ?? 'Docs'),
            'accent' => (string) ($brand['accent'] ?? '#3b82f6'),
            'url' => self::appPath((string) ($brand['url'] ?? '/')),
            'logo' => $brand['logo'] === null ? null : self::appPath((string) $brand['logo']),
            'tables' => (string) config('ai-docs.tables', 'wrap'),
            'theme' => in_array($theme, self::THEMES, strict: true) ? $theme : null,
        ];
    }

    /**
     * A root-relative brand link or logo is relative to the *app*, not the domain — the default
     * `/` means "back to the app", and under a subfolder deploy that is `/myapp/public`, not
     * the host root. Folded through `url()` like {@see self::urlOf()} does for doc links.
     * Anything already absolute is left alone: `https://…`, a protocol-relative `//cdn/…`, a
     * `data:` URI.
     */
    private static function appPath(string $value): string
    {
        if (! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            return $value;
        }

        return parse_url(url($value), PHP_URL_PATH) ?: '/';
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

        $notes = (bool) ($definition['notes'] ?? false);
        $root = $definition['root'] ?? ($notes ? "storage/app/ai-docs/{$key}" : config('ai-docs.root', '.ai/documents'));

        return [
            'key' => $key,
            'path' => trim((string) $path, '/'),
            'root' => trim((string) $root, '/'),
            'label' => (string) ($definition['label'] ?? ucfirst(str_replace(['-', '_'], ' ', $key))),
            'exclude' => array_values(array_map(
                fn (mixed $prefix): string => trim((string) $prefix, '/'),
                (array) $exclude,
            )),
            'notes' => $notes,
            'local' => trim((string) ($definition['local'] ?? ".ai/{$key}"), '/'),
        ];
    }

    /**
     * A note panel's pulled working copy (`.ai/notes`) sits inside the repo, so a repo
     * panel rooted above it (`.ai`) would list every pulled note a second time.
     *
     * @param  array<string, Panel>  $panels
     * @return array<string, Panel>
     */
    private static function excludeWorkingCopies(array $panels): array
    {
        $locals = array_column(array_filter($panels, fn (array $panel): bool => $panel['notes']), 'local');

        return array_map(fn (array $panel): array => [
            ...$panel,
            'exclude' => array_values(array_unique([
                ...$panel['exclude'],
                ...array_map(
                    fn (string $local): string => substr($local, strlen($panel['root']) + 1),
                    array_filter($locals, fn (string $local): bool => str_starts_with($local, $panel['root'].'/')),
                ),
            ])),
        ], $panels);
    }
}
