<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

/**
 * The directives an author attaches to a figure — in an image title
 * (`"inline wide print-80"`) or after `mermaid` in a fence info string — read
 * against a short whitelist, so markdown can never inject an arbitrary class.
 *
 * @see README.md
 */
final readonly class Keywords
{
    /** @var list<string> */
    private const array PRINT = ['50', '60', '70', '80', '90', '100'];

    private function __construct(
        public ?string $print = null,
        public bool $inline = false,
        public bool $wide = false,
        private int $matched = 0,
    ) {}

    /**
     * An image title made of keywords only, or null when it is a real tooltip — a
     * title is either all directives or all prose, never half of each.
     */
    public static function title(?string $title): ?self
    {
        $words = preg_split('/\s+/', trim((string) $title), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywords = self::words($words);

        return $words !== [] && $keywords->matched === count($words) ? $keywords : null;
    }

    /**
     * Whatever keywords appear among the words; anything else is skipped, because a
     * fence info string carries other words too. The first `print-NN` wins.
     *
     * @param  list<string>  $words
     */
    public static function words(array $words): self
    {
        $print = null;
        $inline = false;
        $wide = false;
        $matched = 0;

        foreach ($words as $word) {
            $size = self::print($word);

            $print ??= $size;
            $inline = $inline || $word === 'inline';
            $wide = $wide || $word === 'wide';
            $matched += (int) ($size !== null || $word === 'inline' || $word === 'wide');
        }

        return new self($print, $inline, $wide, $matched);
    }

    /**
     * The class the print stylesheet sizes by, if a size was asked for.
     */
    public function printClass(): ?string
    {
        return $this->print === null ? null : 'doc-print-'.$this->print;
    }

    /**
     * Whichever of the print and bleed classes apply, for an element that takes both.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return array_values(array_filter([$this->printClass(), $this->wide ? 'doc-bleed' : null]));
    }

    private static function print(string $word): ?string
    {
        if (preg_match('/^print-(\d{2,3})$/', $word, $found) !== 1) {
            return null;
        }

        return in_array($found[1], self::PRINT, strict: true) ? $found[1] : null;
    }
}
