<?php

declare(strict_types=1);

use Phattarachai\AiDocs\Support\Keywords;

it('reads a title made only of keywords, in any order', function (): void {
    $keywords = Keywords::title('inline  print-80 wide');

    expect($keywords?->inline)->toBeTrue()
        ->and($keywords?->wide)->toBeTrue()
        ->and($keywords?->print)->toBe('80')
        ->and($keywords?->classes())->toBe(['doc-print-80', 'doc-bleed']);
});

it('treats a title with any other word as a real tooltip', function (string $title): void {
    expect(Keywords::title($title))->toBeNull();
})->with(['A sequence diagram', 'inline please', 'print-42', 'Inline', '', '   ']);

it('picks keywords out of a fence info string and skips the rest', function (): void {
    $keywords = Keywords::words(['title=x', 'print-70', 'wide', 'print-50']);

    expect($keywords->print)->toBe('70')
        ->and($keywords->wide)->toBeTrue()
        ->and($keywords->inline)->toBeFalse()
        ->and($keywords->printClass())->toBe('doc-print-70');
});
