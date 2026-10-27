<?php

declare(strict_types=1);

use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\NotePath;

it('gives a note panel a storage root and a working copy, and hides that copy from a repo panel above it', function (): void {
    config()->set('ai-docs.panels', [
        'all' => ['root' => '.ai', 'exclude' => ['rules']],
        'help' => ['root' => '.ai/handbook', 'exclude' => []],
        'notes' => ['notes' => true],
    ]);

    $panels = AiDocs::panels();

    expect($panels['notes'])->toMatchArray(['root' => 'storage/app/ai-docs/notes', 'local' => '.ai/notes', 'notes' => true])
        ->and($panels['all']['exclude'])->toBe(['rules', 'notes'])
        ->and($panels['help']['exclude'])->toBe([])
        ->and(array_keys(AiDocs::notePanels()))->toBe(['notes'])
        ->and(AiDocs::notePanel(null)['key'] ?? null)->toBe('notes')
        ->and(AiDocs::notePanel('all'))->toBeNull();
});

it('resolves relative image paths without letting them climb out', function (): void {
    $cases = [
        ['flows/cart', 'shot.png', 'flows/cart/shot.png'],
        ['flows/cart', './img/a.png', 'flows/cart/img/a.png'],
        ['flows/cart', '../shared.png', 'flows/shared.png'],
        ['flows', '../../up.png', null],
        ['', '../up.png', null],
    ];

    foreach ($cases as [$dir, $relative, $expected]) {
        expect(NotePath::join($dir, $relative))->toBe($expected, "{$dir} + {$relative}");
    }
});
