<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\Docs;
use Phattarachai\AiDocs\Support\Markdown;

it('inlines an svg marked inline instead of loading it through an img', function (): void {
    $html = Docs::page('diagrams')['html'];

    expect(substr_count($html, '<div class="doc-svg'))->toBe(2)
        ->and($html)->toContain('<div class="doc-wide doc-bleed"><button type="button" class="doc-zoom" aria-label="Full screen"></button><div class="doc-svg doc-print-80" role="figure" aria-label="MJ systems"')
        ->toContain('aria-label="MJ systems &quot;again&quot;"')
        ->toContain('ระบบ MJ')
        ->not->toContain('<p><div');
});

it('strips script, event handlers, foreign content and unsafe links', function (): void {
    $html = Docs::page('diagrams')['html'];

    expect($html)->not->toContain('<script')
        ->not->toContain('alert(')
        ->not->toContain('onload')
        ->not->toContain('onclick')
        ->not->toContain('foreignObject')
        ->not->toContain('html inside')
        ->not->toContain('javascript:')
        ->not->toContain('data:image')
        ->not->toContain('evil.test')
        ->not->toContain('<set')
        ->not->toContain('<font')
        ->not->toContain('inkscape')
        ->not->toContain('<!--');
});

it('keeps defs, markers, gradients, styles and text', function (): void {
    $html = Docs::page('diagrams')['html'];

    expect($html)->toContain('<marker id="ds-')
        ->toContain('<linearGradient id="ds-')
        ->toContain('<style>')
        ->toContain('fill: var(--doc-ok-bg, #dcfce7)')
        ->toContain('Labels card');
});

it('namespaces every id and reference per figure, so a repeated drawing never collides', function (): void {
    $html = Docs::page('diagrams')['html'];

    preg_match_all('/data-doc-ns="(ds-[0-9a-f]{6}-\d+-)"/', $html, $found);
    [$first, $second] = $found[1];

    expect($first)->not->toBe($second)
        ->and($html)->toContain('id="'.$first.'arrow"')
        ->toContain('id="'.$second.'arrow"')
        ->toContain('marker-end="url(#'.$first.'arrow)"')
        ->toContain('fill="url(#'.$first.'bG)"')
        ->toContain('<use href="#'.$first.'box"')
        ->toContain('#'.$first.'arrow path')
        ->not->toContain('id="arrow"')
        ->not->toContain('url(#arrow)');
});

it('scopes the drawing\'s own stylesheet to its root', function (): void {
    $html = Docs::page('diagrams')['html'];

    preg_match('/data-doc-ns="(ds-[0-9a-f]{6}-1-)"/', $html, $found);
    $root = '#'.rtrim($found[1], '-');

    expect($html)->toContain('<svg xmlns="http://www.w3.org/2000/svg" width="100%" viewBox="0 0 1200 700" id="'.ltrim($root, '#').'">')
        ->toContain($root.' { font-family: inherit; }')
        ->toContain($root.' .card {')
        ->toContain($root.' '.$root.'-arrow path {')
        ->toContain($root.' text, '.$root.' .label &gt; tspan {')
        ->toContain('background: none;')
        ->not->toContain('@import');
});

it('drops the fixed size and keeps the viewBox', function (): void {
    $html = Docs::page('diagrams')['html'];

    expect($html)->not->toContain('width="1200"')
        ->not->toContain('height="700"')
        ->toContain('viewBox="0 0 1200 700"');
});

it('resolves a card link through the doc resolver and keeps page anchors', function (): void {
    $html = Docs::page('diagrams')['html'];

    expect($html)->toContain('<a href="/docs/guides/labels#front-matter">')
        ->toContain('<a href="#flow">')
        ->toContain('<a><text x="20" y="150">Away</text></a>');
});

it('leaves an svg as an img without the keyword or when it shares a line', function (): void {
    $html = Docs::page('diagrams')['html'];

    expect($html)->toContain('<img src="/docs/_media/diagrams.svg" alt="Plain" />')
        ->toContain('<img src="/docs/_media/diagrams.svg" alt="Beside text" /> with words after it.');
});

it('never inlines a remote svg', function (): void {
    $html = Markdown::render('![R](https://example.test/x.svg "inline")', '')['html'];

    expect($html)->toContain('<img src="https://example.test/x.svg" alt="R" />');
});

it('falls back to an img when the file is not well-formed svg', function (): void {
    $file = AiDocs::root().'/broken.svg';
    file_put_contents($file, '<svg><g></svg>');

    try {
        expect(Markdown::render('![B](broken.svg "inline")', '')['html'])
            ->toContain('<img src="/docs/_media/broken.svg" alt="B" />');
    } finally {
        @unlink($file);
    }
});

it('refuses an svg that declares entities', function (): void {
    $file = AiDocs::root().'/entity.svg';
    file_put_contents($file, '<!DOCTYPE svg [<!ENTITY a "aaaa">]><svg xmlns="http://www.w3.org/2000/svg"><text>&a;</text></svg>');

    try {
        expect(Markdown::render('![E](entity.svg "inline")', '')['html'])->toContain('<img src="/docs/_media/entity.svg"');
    } finally {
        @unlink($file);
    }
});

it('indexes the drawing\'s text under the section that holds it', function (): void {
    $sections = collect(Docs::sections('diagrams'))->keyBy('id');

    expect($sections['landscape']['text'])->toContain('ระบบ MJ')->toContain('Labels card')
        ->and($sections['not-inline']['text'])->not->toContain('Labels card');
});

it('re-renders a cached page when only the svg changes', function (): void {
    config()->set('ai-docs.cache', value: true);

    $md = AiDocs::root().'/cached.md';
    $svg = AiDocs::root().'/cached.svg';
    file_put_contents($md, "# Cached\n\n![C](cached.svg \"inline\")\n");
    file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><text>first</text></svg>');
    touch($svg, time() - 60);
    clearstatcache();

    try {
        expect(Docs::page('cached')['html'])->toContain('first');

        file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><text>second</text></svg>');
        touch($svg, time());
        clearstatcache();

        expect(Docs::page('cached')['html'])->toContain('second');
    } finally {
        @unlink($md);
        @unlink($svg);
        Cache::flush();
    }
});
