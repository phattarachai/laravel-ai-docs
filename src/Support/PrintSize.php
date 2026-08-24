<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

/**
 * The opt-in per-element print size an author attaches — `print-70` on a mermaid
 * fence, or in an image title — mapped to the wrapper class the print stylesheet
 * understands. A short whitelist, so markdown can never inject an arbitrary
 * class. @see README.md
 */
final class PrintSize
{
    /** @var list<string> */
    private const array ALLOWED = ['50', '60', '70', '80', '90', '100'];

    public static function classFor(?string $token): ?string
    {
        if ($token === null || preg_match('/^print-(\d{2,3})$/', trim($token), $found) !== 1) {
            return null;
        }

        return in_array($found[1], self::ALLOWED, strict: true) ? 'doc-print-'.$found[1] : null;
    }
}
