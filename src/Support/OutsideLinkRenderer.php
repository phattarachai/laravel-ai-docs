<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/** @see README.md */
final class OutsideLinkRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?HtmlElement
    {
        if ($node->data->get('doc_outside', default: false) !== true) {
            return null;
        }

        return new HtmlElement(
            'span',
            ['class' => 'doc-ext'],
            $childRenderer->renderNodes($node->children()),
        );
    }
}
