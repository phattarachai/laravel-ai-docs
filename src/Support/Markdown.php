<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Util\Xml;

/** @see README.md */
final class Markdown
{
    private const int SECTION_LIMIT = 1000;

    /**
     * @param  string  $docDir  the page's own directory, relative to the docs root ('' at root)
     * @return array{html: string, toc: list<array{id: string, text: string, level: int}>, sections: list<array{id: string, heading: string, level: int, text: string}>}
     */
    public static function render(string $markdown, string $docDir): array
    {
        $toc = [];
        $sections = [];

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension);

        $environment->addEventListener(
            DocumentParsedEvent::class,
            function (DocumentParsedEvent $event) use (&$toc, &$sections, $docDir): void {
                self::walk($event->getDocument(), $toc, $sections, $docDir);
            },
        );

        $environment->addRenderer(Link::class, new OutsideLinkRenderer, 10);
        $environment->addRenderer(Image::class, new OutsideLinkRenderer, 10);
        $environment->addRenderer(BlockQuote::class, new CalloutRenderer, 10);
        $environment->addRenderer(FencedCode::class, new MermaidRenderer, 10);
        $environment->addRenderer(FencedCode::class, new CodeRenderer, 9);

        $html = (string) new MarkdownConverter($environment)->convert($markdown);

        return ['html' => self::tables($html), 'toc' => $toc, 'sections' => $sections];
    }

    public static function version(): string
    {
        return (string) @filemtime(__FILE__);
    }

    public static function text(Node $node): string
    {
        $out = '';

        foreach ($node->iterator() as $child) {
            if ($child instanceof Text || $child instanceof Code) {
                $out .= $child->getLiteral();
            }
        }

        return trim($out);
    }

    public static function wideOpen(): string
    {
        return '<div class="doc-wide"><button type="button" class="doc-zoom" aria-label="'
            .Xml::escape((string) trans('ai-docs::ui.zoom.open')).'"></button>';
    }

    private static function tables(string $html): string
    {
        return str_replace(
            ['<table>', '</table>'],
            [self::wideOpen().'<div class="doc-tablebox"><table>', '</table></div></div>'],
            $html,
        );
    }

    /**
     * @param  list<array{id: string, text: string, level: int}>  $toc
     * @param  list<array{id: string, heading: string, level: int, text: string}>  $sections
     */
    private static function walk(Document $document, array &$toc, array &$sections, string $docDir): void
    {
        $seen = [];
        $inHeading = false;
        $walker = $document->walker();

        while ($event = $walker->next()) {
            $node = $event->getNode();

            if ($node instanceof Heading) {
                $inHeading = $event->isEntering();

                if ($event->isEntering()) {
                    self::heading($node, $toc, $sections, $seen);
                }

                continue;
            }

            if (! $event->isEntering()) {
                continue;
            }

            if ($node instanceof Link) {
                Links::rewrite($node, $docDir);
            }

            if ($node instanceof Image) {
                Links::image($node, $docDir);
                self::printSize($node);
            }

            if ($node instanceof BlockQuote) {
                self::alert($node);
            }

            if (! $inHeading && ($node instanceof Text || $node instanceof Code)) {
                self::collect($sections, $node->getLiteral());
            }
        }
    }

    /**
     * A `print-70` image title is a print directive, not a tooltip: turn it into
     * the wrapper class and drop the title so nothing hovers on screen.
     */
    private static function printSize(Image $image): void
    {
        $class = PrintSize::classFor($image->getTitle());

        if ($class === null) {
            return;
        }

        $attributes = (array) $image->data->get('attributes');
        $attributes['class'] = trim(($attributes['class'] ?? '').' '.$class);

        $image->data->set('attributes', $attributes);
        $image->setTitle(null);
    }

    private static function alert(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();

        if (! $paragraph instanceof Paragraph) {
            return;
        }

        if (preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]/i', self::text($paragraph), $found) !== 1) {
            return;
        }

        $eaten = '';

        foreach ($paragraph->children() as $child) {
            if (mb_strlen($eaten) >= mb_strlen($found[0])) {
                break;
            }

            $eaten .= $child instanceof Text || $child instanceof Code ? $child->getLiteral() : '';
            $child->detach();
        }

        $spare = mb_substr($eaten, mb_strlen($found[0]));

        if (trim($spare) !== '') {
            $paragraph->prependChild(new Text(ltrim($spare)));
        }

        if ($paragraph->firstChild() instanceof Newline) {
            $paragraph->firstChild()->detach();
        }

        $quote->data->set('doc_alert', mb_strtolower($found[1]));
    }

    /**
     * @param  list<array{id: string, heading: string, level: int, text: string}>  $sections
     */
    private static function collect(array &$sections, string $literal): void
    {
        $last = array_key_last($sections);

        if ($last === null || mb_strlen($sections[$last]['text']) >= self::SECTION_LIMIT) {
            return;
        }

        $sections[$last]['text'] = mb_substr(
            trim($sections[$last]['text'].' '.$literal),
            0,
            self::SECTION_LIMIT,
        );
    }

    /**
     * @param  list<array{id: string, text: string, level: int}>  $toc
     * @param  list<array{id: string, heading: string, level: int, text: string}>  $sections
     * @param  array<string, int>  $seen
     */
    private static function heading(Heading $heading, array &$toc, array &$sections, array &$seen): void
    {
        $text = self::text($heading);
        $id = Slug::make($text, $seen);

        $attributes = (array) $heading->data->get('attributes');
        $heading->data->set('attributes', ['id' => $id] + $attributes);
        $heading->appendChild(self::permalink($id));

        if ($heading->getLevel() > 1) {
            $toc[] = ['id' => $id, 'text' => $text, 'level' => $heading->getLevel()];
        }

        $sections[] = ['id' => $id, 'heading' => $text, 'level' => $heading->getLevel(), 'text' => ''];
    }

    private static function permalink(string $id): Link
    {
        $link = new Link('#'.$id);

        $link->appendChild(new Text('#'));
        $link->data->set('attributes', [
            'class' => 'doc-anchor',
            'aria-label' => (string) trans('ai-docs::ui.anchor.label'),
        ]);

        return $link;
    }
}
