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

        $class = 'doc-mermaid';

        foreach (array_slice($words, 1) as $word) {
            if (($size = PrintSize::classFor($word)) !== null) {
                $class .= ' '.$size;

                break;
            }
        }

        return new HtmlElement('div', ['class' => 'doc-wide'], [
            new HtmlElement('button', ['type' => 'button', 'class' => 'doc-zoom', 'aria-label' => (string) trans('ai-docs::ui.zoom.open')]),
            new HtmlElement('div', ['class' => $class, 'data-src' => $node->getLiteral()]),
        ]);
    }
}
