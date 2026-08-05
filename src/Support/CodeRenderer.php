<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use Tempest\Highlight\Highlighter;
use Tempest\Highlight\Themes\CssTheme;

/** @see README.md */
final class CodeRenderer implements NodeRendererInterface
{
    private static ?Highlighter $highlighter = null;

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?HtmlElement
    {
        if (! $node instanceof FencedCode) {
            return null;
        }

        $language = mb_strtolower($node->getInfoWords()[0] ?? '');

        self::$highlighter ??= new Highlighter(new CssTheme);

        return new HtmlElement(
            'pre',
            ['class' => 'doc-code', 'data-lang' => $language],
            new HtmlElement('code', [], self::$highlighter->parse($node->getLiteral(), $language ?: 'txt')),
        );
    }
}
