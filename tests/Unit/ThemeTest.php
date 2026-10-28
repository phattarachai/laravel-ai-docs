<?php

declare(strict_types=1);

use Phattarachai\AiDocs\AiDocs;

it('follows the reader’s OS when no default theme is configured', function (): void {
    config()->set('ai-docs.theme', null);

    expect(AiDocs::brand()['theme'])->toBeNull();
});

it('hands a configured default theme to the panel', function (string $theme): void {
    config()->set('ai-docs.theme', $theme);

    expect(AiDocs::brand()['theme'])->toBe($theme);
})->with(AiDocs::THEMES);

it('ignores a theme the picker does not offer', function (): void {
    config()->set('ai-docs.theme', 'solarized');

    expect(AiDocs::brand()['theme'])->toBeNull();
});

it('keeps the PHP and JS theme lists, their labels and their colours in step', function (): void {
    $js = (string) file_get_contents(__DIR__.'/../../resources/js/ai-docs/scheme.js');
    $css = (string) file_get_contents(__DIR__.'/../../resources/js/ai-docs/ai-docs.css');
    $mermaid = (string) file_get_contents(__DIR__.'/../../resources/js/ai-docs/mermaid-theme.js');

    preg_match('/export const SCHEMES = \[([^\]]*)\]/', $js, $match);
    preg_match_all("/'([a-z]+)'/", $match[1] ?? '', $schemes);

    expect($schemes[1])->toBe(AiDocs::THEMES);

    foreach (['en', 'th'] as $locale) {
        $strings = require __DIR__."/../../lang/{$locale}/ui.php";

        foreach (AiDocs::THEMES as $theme) {
            expect($strings)->toHaveKey("theme.{$theme}");
        }
    }

    foreach (AiDocs::THEMES as $theme) {
        expect($css)->toContain(".doc-swatch.is-{$theme}")
            ->and($mermaid)->toMatch("/^  {$theme}: \\{/m");
    }
});
