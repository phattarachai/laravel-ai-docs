<?php

declare(strict_types=1);

use Phattarachai\AiDocs\Support\Markdown;

it('turns a GitHub alert into a callout and leaves a plain quote alone', function (): void {
    $html = Markdown::render("> [!WARNING]\n> Risky.\n\n> Just a quote.\n", '')['html'];

    expect($html)->toContain('<div class="doc-callout tone-warning"><p class="doc-callout-hd">Warning</p>')
        ->toContain('<p>Risky.</p>')
        ->toContain('<blockquote>')
        ->not->toContain('[!WARNING]');
});

it('renders the callout heading in the active locale', function (): void {
    app()->setLocale('th');

    $html = Markdown::render("> [!WARNING]\n> Risky.\n", '')['html'];

    expect($html)->toContain('<p class="doc-callout-hd">คำเตือน</p>')
        ->not->toContain('Warning');
});

it('highlights a fence server-side and never lets its contents become markup', function (): void {
    $html = Markdown::render("```php\nfinal class A {}\n```\n\n```txt\n<script>x</script>\n```\n", '')['html'];

    expect($html)->toContain('<span class="hl-keyword">final</span>')
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>');
});

it('sizes a mermaid diagram for print from a fence keyword', function (): void {
    $html = Markdown::render("```mermaid print-70\nflowchart TD\nA-->B\n```\n", '')['html'];

    expect($html)->toContain('class="doc-mermaid doc-print-70"');
});

it('sizes an image for print from its title and drops the title', function (): void {
    $html = Markdown::render('![A](https://example.test/y.png "print-80")', '')['html'];

    expect($html)->toContain('doc-print-80')
        ->not->toContain('title=');
});

it('ignores a print size outside the whitelist', function (): void {
    $html = Markdown::render("```mermaid print-42\nflowchart TD\nA-->B\n```\n", '')['html'];

    expect($html)->toContain('class="doc-mermaid"')
        ->not->toContain('doc-print');
});

it('lets a mermaid diagram or an image bleed wide, alongside a print size', function (): void {
    $html = Markdown::render("```mermaid wide print-70\nflowchart TD\nA-->B\n```\n\n![A](https://example.test/y.png \"wide inline\")\n", '')['html'];

    expect($html)->toContain('<div class="doc-wide doc-bleed"><button')
        ->toContain('class="doc-mermaid doc-print-70"')
        ->toContain('<img class="doc-bleed" src="https://example.test/y.png" alt="A" />');
});

it('keeps an ordinary image title as a tooltip', function (): void {
    expect(Markdown::render('![A](https://example.test/y.png "Hover me")', '')['html'])
        ->toContain('title="Hover me"');
});
