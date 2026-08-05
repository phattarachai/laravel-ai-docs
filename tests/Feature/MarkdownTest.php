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
