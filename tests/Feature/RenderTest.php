<?php

declare(strict_types=1);

use Phattarachai\AiDocs\Support\Docs;
use Phattarachai\AiDocs\Support\Markdown;

it('rewrites a doc-to-doc link and defuses one pointing out of the root', function (): void {
    $html = Docs::page('links')['html'];

    expect($html)->toContain('href="/docs/guides/labels"')
        ->toContain('<span class="doc-ext">composer manifest</span>')
        ->not->toContain('composer.json"');
});

it('opens an external link in a new tab', function (): void {
    expect(Docs::page('links')['html'])
        ->toContain('target="_blank"')
        ->toContain('rel="noreferrer noopener"')
        ->toContain('https://example.test/docs');
});

it('hands a mermaid fence to the client instead of rendering it', function (): void {
    $html = Docs::page('guides/getting-started')['html'];

    expect(substr_count($html, 'class="doc-mermaid"'))->toBe(1)
        ->and($html)->not->toContain('<code class="language-mermaid"')
        ->not->toContain('language-mermaid');
});

it('reports the repo-relative markdown path and a translated permalink anchor', function (): void {
    $page = Docs::page('guides/getting-started');

    expect($page['path'])->toBe('.ai/documents/guides/getting-started.md')
        ->and($page['html'])->toContain('<a class="doc-anchor" aria-label="Link to this section" href="#flow">');
});

it('builds a table of contents from every heading below the H1', function (): void {
    expect(Docs::page('guides/getting-started')['toc'])->toBe([
        ['id' => 'flow', 'text' => 'Flow', 'level' => 2],
        ['id' => 'code', 'text' => 'Code', 'level' => 2],
    ]);
});

it('wraps every table and diagram in a block the panel can open full screen', function (): void {
    $html = Markdown::render("| a | b |\n|---|---|\n| 1 | 2 |\n\n```mermaid\ngraph TD\n  A-->B\n```\n", '')['html'];

    expect(substr_count($html, '<div class="doc-wide">'))->toBe(2)
        ->and(substr_count($html, 'class="doc-zoom"'))->toBe(2)
        ->and($html)->toContain('<div class="doc-wide"><button type="button" class="doc-zoom" aria-label="Full screen"></button><div class="doc-tablebox"><table>');
});

it('still rewrites links when the configured root climbs out of the project', function (): void {
    // `AI_DOCS_ROOT=../shared/docs` is a legitimate layout. Without normalising, the
    // un-resolved `..` never prefix-matched the resolved link target, so every
    // doc-to-doc link silently degraded to plain text.
    config()->set('ai-docs.root', '.ai/../.ai/documents');

    expect(Docs::page('links')['html'])->toContain('href="/docs/guides/labels"');
});
