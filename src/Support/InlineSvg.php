<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use DOMDocument;
use DOMElement;
use DOMText;

/**
 * A hand-drawn SVG beside a doc, inlined into the page rather than loaded through
 * `<img>` — so it inherits the panel's fonts and `--doc-*` tokens, and its links work.
 *
 * Built by copying the source into a fresh document, never by deleting from it: only
 * whitelisted elements and attributes are ever written, so anything the copier does not
 * know about is simply absent. Every id is namespaced per figure, and the `<style>` is
 * scoped to the figure's own root, because an inline SVG shares the page's id space and
 * stylesheet.
 *
 * @see README.md — "Hand-drawn SVG diagrams"
 * @see docs/internals.md — "An inlined SVG is copied, not cleaned"
 */
final class InlineSvg
{
    private const string NS = 'http://www.w3.org/2000/svg';

    private const string XLINK = 'http://www.w3.org/1999/xlink';

    private const int MAX_BYTES = 2_000_000;

    /**
     * Drawing elements only. Absent on purpose: `script`; `foreignObject` (HTML inside
     * SVG); every SMIL element, since `<set attributeName="href">` writes a link after
     * sanitizing; `feImage`, which fetches; and `font`, which the HTML parser treats as
     * a breakout tag and leaves the SVG for.
     *
     * @var list<string>
     */
    private const array ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'switch', 'title', 'desc', 'style', 'a', 'image',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textPath',
        'marker', 'linearGradient', 'radialGradient', 'stop', 'pattern', 'clipPath', 'mask',
        'filter', 'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix',
        'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood', 'feFuncA',
        'feFuncB', 'feFuncG', 'feFuncR', 'feGaussianBlur', 'feMerge', 'feMergeNode', 'feMorphology',
        'feOffset', 'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile', 'feTurbulence',
    ];

    /** @var array<string, true> */
    private array $ids = [];

    private string $scope = '';

    private function __construct(private readonly string $docDir, private readonly string $ns) {}

    /**
     * The sanitized markup and its `<text>` content, or null when the file is not an SVG
     * worth inlining — the caller then keeps its plain `<img>`.
     *
     * @param  string  $ns  the id prefix, unique per figure on the page
     * @return array{html: string, text: string}|null
     */
    public static function render(string $absolute, string $docDir, string $ns): ?array
    {
        $root = self::load($absolute);

        return $root === null ? null : new self($docDir, $ns)->build($root);
    }

    private static function load(string $absolute): ?DOMElement
    {
        $xml = is_file($absolute) && filesize($absolute) <= self::MAX_BYTES ? file_get_contents($absolute) : false;

        if (! is_string($xml) || trim($xml) === '') {
            return null;
        }

        $source = new DOMDocument;
        $errors = libxml_use_internal_errors(true);

        try {
            $loaded = $source->loadXML($xml, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($errors);
        }

        $root = $source->documentElement;

        // An entity declaration buys nothing in a diagram and is how an XML bomb starts.
        if (! $loaded || ($source->doctype?->entities->length ?? 0) > 0
            || ! $root instanceof DOMElement || $root->localName !== 'svg' || ! self::allowed($root)) {
            return null;
        }

        return $root;
    }

    /**
     * @return array{html: string, text: string}
     */
    private function build(DOMElement $root): array
    {
        foreach ($root->ownerDocument?->getElementsByTagName('*') ?? [] as $element) {
            if ($element->hasAttribute('id')) {
                $this->ids[$element->getAttribute('id')] = true;
            }
        }

        $this->scope = $root->hasAttribute('id') ? $this->ns.$root->getAttribute('id') : rtrim($this->ns, '-');

        $out = new DOMDocument('1.0', 'UTF-8');
        $svg = $this->copy($root, $out);
        $out->appendChild($svg);

        $this->size($root, $svg);

        $text = [];

        foreach ($out->getElementsByTagNameNS(self::NS, 'text') as $label) {
            $text[] = trim((string) preg_replace('/\s+/u', ' ', $label->textContent));
        }

        return [
            'html' => (string) $out->saveXML($svg),
            'text' => implode(' ', array_filter($text, fn (string $each): bool => $each !== '')),
        ];
    }

    private function copy(DOMElement $from, DOMDocument $out): DOMElement
    {
        $to = $out->createElementNS(self::NS, $from->localName);

        foreach ($from->attributes ?? [] as $attribute) {
            $name = self::name($attribute->namespaceURI, $attribute->localName);
            $value = $name === null ? null : $this->attribute($from->localName, $name, $attribute->value);

            if ($value !== null) {
                $to->setAttribute($name, $value);
            }
        }

        if ($from->localName === 'style') {
            $to->appendChild($out->createTextNode($this->css($from->textContent)));

            return $to;
        }

        foreach ($from->childNodes as $child) {
            if ($child instanceof DOMElement && self::allowed($child)) {
                $to->appendChild($this->copy($child, $out));
            }

            // Comments, processing instructions and entity references never cross over.
            if ($child instanceof DOMText) {
                $to->appendChild($out->createTextNode($child->data));
            }
        }

        return $to;
    }

    /**
     * Responsive: the viewBox carries the aspect ratio, the column carries the width.
     */
    private function size(DOMElement $source, DOMElement $svg): void
    {
        $width = (float) $source->getAttribute('width');
        $height = (float) $source->getAttribute('height');

        if (! $svg->hasAttribute('viewBox') && $width > 0 && $height > 0) {
            $svg->setAttribute('viewBox', "0 0 {$width} {$height}");
        }

        $svg->removeAttribute('height');
        $svg->setAttribute('width', '100%');
        $svg->setAttribute('id', $this->scope);
    }

    private static function allowed(DOMElement $element): bool
    {
        return in_array($element->namespaceURI, [null, '', self::NS], strict: true)
            && in_array($element->localName, self::ELEMENTS, strict: true);
    }

    /**
     * Plain attributes keep their name and `xlink:href` becomes SVG 2's `href`; every
     * other namespaced attribute — `inkscape:*`, `sodipodi:*`, `xml:*` — is dropped.
     */
    private static function name(?string $namespace, ?string $local): ?string
    {
        return match (true) {
            $local === null => null,
            $namespace === null || $namespace === '' => $local,
            $namespace === self::XLINK && $local === 'href' => 'href',
            default => null,
        };
    }

    private function attribute(string $element, string $name, string $value): ?string
    {
        $lower = mb_strtolower($name);

        return match (true) {
            str_starts_with($lower, 'on') => null,
            $lower === 'id' => $this->ns.$value,
            $lower === 'href' => $this->href($element, trim($value)),
            $lower === 'aria-labelledby', $lower === 'aria-describedby' => $this->idList($value),
            default => $this->urls($value),
        };
    }

    /**
     * A `#fragment` stays inside the figure; a relative link from a card goes through
     * the same resolver as a markdown link, across panels too. Everything else — an
     * external host, `javascript:`, `data:` — is dropped.
     */
    private function href(string $element, string $url): ?string
    {
        if (str_starts_with($url, '#')) {
            $id = substr($url, 1);

            // An anchor to a heading on the page, not to a shape in the figure.
            return $element === 'a' && ! isset($this->ids[$id]) ? $url : '#'.$this->ns.$id;
        }

        return match ($element) {
            'a' => Links::href($url, $this->docDir),
            'image' => Links::mediaUrl($url, $this->docDir),
            default => null,
        };
    }

    private function idList(string $value): string
    {
        $ids = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $id): string => isset($this->ids[$id]) ? $this->ns.$id : $id, $ids));
    }

    /**
     * `url(#marker)` follows its namespaced id; any other `url()` would fetch, so it goes.
     */
    private function urls(string $value): string
    {
        if (stripos($value, 'url(') === false) {
            return $value;
        }

        return (string) preg_replace_callback(
            '/url\(\s*([\'"]?)(.*?)\1\s*\)/i',
            fn (array $found): string => str_starts_with(trim($found[2]), '#')
                ? 'url(#'.$this->ns.substr(trim($found[2]), 1).')'
                : 'none',
            $value,
        );
    }

    /**
     * Scope every rule to this figure's root — an inline `<style>` is page-wide, so a
     * bare `.card` or `text` rule would otherwise restyle the doc around it. A leading
     * `svg` or `:root` becomes the root itself. Statement at-rules (`@import`) go;
     * keyframe selectors and declaration blocks are left alone apart from `url()`.
     */
    private function css(string $css): string
    {
        $css = (string) preg_replace(['#/\*.*?\*/#s', '/@[a-z-]+[^;{}]*;/i'], '', $css);
        $parts = preg_split('/([{}])/', $css, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $stack = [];
        $out = '';

        for ($i = 0; $i < count($parts); $i += 2) {
            $text = $parts[$i];
            $brace = $parts[$i + 1] ?? '';

            if ($brace === '{') {
                $prelude = trim($text);
                $top = end($stack);

                [$kind, $written] = match (true) {
                    preg_match('/^@(-[a-z]+-)?keyframes\b/i', $prelude) === 1 => ['keyframes', $prelude],
                    str_starts_with($prelude, '@') => ['group', $prelude],
                    $top === 'keyframes' => ['rule', $prelude],
                    $top === 'rule' => ['rule', $this->selectors($prelude, scoped: false)],
                    default => ['rule', $this->selectors($prelude, scoped: true)],
                };

                $stack[] = $kind;
                $out .= $written.' {';

                continue;
            }

            $out .= $this->urls($text).$brace;

            if ($brace === '}') {
                array_pop($stack);
            }
        }

        return $out;
    }

    private function selectors(string $prelude, bool $scoped): string
    {
        $selectors = [];

        foreach (self::split($prelude) as $selector) {
            $selector = (string) preg_replace_callback(
                '/#([\w\x{80}-\x{10FFFF}-]+)/u',
                fn (array $found): string => isset($this->ids[$found[1]]) ? '#'.$this->ns.$found[1] : $found[0],
                trim($selector),
            );

            $selectors[] = match (true) {
                ! $scoped, preg_match('/^#'.preg_quote($this->scope, '/').'(?![\w-])/', $selector) === 1 => $selector,
                preg_match('/^(svg|:root)(?![\w-])/i', $selector, $found) === 1 => '#'.$this->scope.substr($selector, strlen($found[0])),
                default => '#'.$this->scope.' '.$selector,
            };
        }

        return implode(', ', $selectors);
    }

    /**
     * A selector list split on its top-level commas, so `:is(a, b)` stays whole.
     *
     * @return list<string>
     */
    private static function split(string $prelude): array
    {
        $parts = [''];
        $depth = 0;

        foreach (mb_str_split($prelude) as $char) {
            $depth += match ($char) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($char === ',' && $depth === 0) {
                $parts[] = '';

                continue;
            }

            $parts[array_key_last($parts)] .= $char;
        }

        return array_values(array_filter($parts, fn (string $part): bool => trim($part) !== ''));
    }
}
