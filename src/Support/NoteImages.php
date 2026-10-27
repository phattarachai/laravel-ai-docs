<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Parser\MarkdownParser;

/**
 * The local images a markdown page shows, which travel with it on push and pull.
 */
final class NoteImages
{
    /**
     * Relative image URLs, decoded, without query or fragment, in page order.
     *
     * @return list<string>
     */
    public static function of(string $markdown): array
    {
        $environment = new Environment;
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new FrontMatterExtension);

        $images = [];

        foreach (new MarkdownParser($environment)->parse($markdown)->iterator() as $node) {
            if ($node instanceof Image && self::local($node->getUrl())) {
                $images[] = rawurldecode(self::path($node->getUrl()));
            }
        }

        return array_values(array_unique($images));
    }

    private static function local(string $url): bool
    {
        $path = self::path($url);

        return $path !== ''
            && ! str_starts_with($path, '/')
            && preg_match('#^[a-z][a-z0-9+.-]*:#i', $path) !== 1
            && in_array(mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)), Docs::IMAGES, strict: true);
    }

    private static function path(string $url): string
    {
        return (string) preg_replace('/[?#].*$/s', '', $url);
    }
}
