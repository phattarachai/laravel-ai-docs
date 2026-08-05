<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;

/** @see README.md */
final class CalloutRenderer implements NodeRendererInterface
{
    private const array TONES = ['note', 'tip', 'important', 'warning', 'caution'];

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): ?HtmlElement
    {
        $tone = $node->data->get('doc_alert', default: null);

        if (! is_string($tone) || ! in_array($tone, self::TONES, strict: true)) {
            return null;
        }

        return new HtmlElement('div', ['class' => 'doc-callout tone-'.$tone], [
            new HtmlElement('p', ['class' => 'doc-callout-hd'], Xml::escape((string) trans('ai-docs::ui.callout.'.$tone))),
            $childRenderer->renderNodes($node->children()),
        ]);
    }
}
