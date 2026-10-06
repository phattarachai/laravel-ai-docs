<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * The same `.doc-wide` + zoom wrapper a mermaid diagram gets, so full screen and
 * `print-NN` work on it unchanged. @see README.md — "Hand-drawn SVG diagrams"
 */
final class SvgFigureRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?HtmlElement
    {
        if (! $node instanceof SvgFigure) {
            return null;
        }

        return new HtmlElement('div', ['class' => $node->keywords->wide ? 'doc-wide doc-bleed' : 'doc-wide'], [
            new HtmlElement('button', ['type' => 'button', 'class' => 'doc-zoom', 'aria-label' => (string) trans('ai-docs::ui.zoom.open')]),
            new HtmlElement('div', [
                'class' => implode(' ', array_filter(['doc-svg', $node->keywords->printClass()])),
                'role' => 'figure',
                'aria-label' => $node->label,
                'data-doc-ns' => $node->ns,
            ], $node->svg),
        ]);
    }
}
