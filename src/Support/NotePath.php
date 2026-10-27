<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

/**
 * A path inside a note panel that the panel can route to: segments of letters,
 * digits, `_`, `.` and `-`, none hidden and none climbing out.
 *
 * @see README.md — "Notes"
 */
final class NotePath
{
    public const array EXTENSIONS = ['md', ...Docs::IMAGES];

    /**
     * A note or category path: `flows/cart/price-recalc`.
     */
    public static function note(string $path): ?string
    {
        $segments = explode('/', trim($path, '/'));

        foreach ($segments as $segment) {
            if (preg_match('/^[\p{L}\p{N}_-][\p{L}\p{N}_.-]*$/u', $segment) !== 1) {
                return null;
            }
        }

        return implode('/', $segments);
    }

    /**
     * A file a push may write: a note path ending in `.md` or an image extension.
     */
    public static function file(string $path): ?string
    {
        $normalized = str_starts_with($path, '/') ? null : self::note($path);
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $normalized !== null && in_array($extension, self::EXTENSIONS, strict: true) ? $normalized : null;
    }

    /**
     * `$relative` resolved against the directory `$dir`, with `.` and `..` folded away,
     * or null when it climbs above where `$dir` is measured from.
     */
    public static function join(string $dir, string $relative): ?string
    {
        $resolved = [];

        foreach (explode('/', $dir.'/'.$relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..' && $resolved === []) {
                return null;
            }

            if ($segment === '..') {
                array_pop($resolved);

                continue;
            }

            $resolved[] = $segment;
        }

        return $resolved === [] ? null : implode('/', $resolved);
    }
}
