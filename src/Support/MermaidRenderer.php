<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/** @see README.md */
final class MermaidRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?HtmlElement
    {
        if (! $node instanceof FencedCode) {
            return null;
        }

        $words = $node->getInfoWords();

        if (mb_strtolower($words[0] ?? '') !== 'mermaid') {
            return null;
        }

        $keywords = Keywords::words(array_values(array_slice($words, 1)));
        $class = implode(' ', array_filter(['doc-mermaid', $keywords->printClass()]));

        return new HtmlElement('div', ['class' => $keywords->wide ? 'doc-wide doc-bleed' : 'doc-wide'], [
            new HtmlElement('button', ['type' => 'button', 'class' => 'doc-zoom', 'aria-label' => (string) trans('ai-docs::ui.zoom.open')]),
            new HtmlElement('div', ['class' => $class, 'data-src' => $node->getLiteral()]),
        ]);
    }
}
