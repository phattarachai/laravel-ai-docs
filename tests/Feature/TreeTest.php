<?php

declare(strict_types=1);

use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\Docs;
use Phattarachai\AiDocs\Support\DocTree;

it('lists every doc outside the excluded prefixes, and nothing inside them', function (): void {
    expect(array_column(DocTree::flatten(), 'slug'))->toEqualCanonicalizing([
        'index',
        'links',
        'diagrams',
        'private',
        'guides/getting-started',
        'guides/labels',
        'guides/advanced/caching',
        'guides/advanced/queues',
    ]);
});

it('keeps the directory but not the file when both share an excluded name', function (): void {
    expect(AiDocs::excluded('private/secret.md'))->toBeTrue()
        ->and(AiDocs::excluded('private.md'))->toBeFalse()
        ->and(Docs::page('private/secret'))->toBeNull()
        ->and(Docs::page('private'))->not->toBeNull();
});

it('groups the root apart from each directory and labels it from the folder name', function (): void {
    $groups = DocTree::groups();

    expect(array_column($groups, 'key'))->toBe(['_root', 'guides'])
        ->and($groups[1]['label'])->toBe('Guides');
});

it('nests a subfolder under its parent instead of listing it as another top-level group', function (): void {
    $guides = DocTree::groups()[1];
    $advanced = $guides['groups'][0];

    expect(array_column($guides['groups'], 'key'))->toBe(['guides/advanced'])
        ->and($advanced['label'])->toBe('Advanced')
        ->and(array_column($advanced['items'], 'slug'))
        ->toBe(['guides/advanced/caching', 'guides/advanced/queues']);
});

it('walks the nested tree depth first and names each doc its full group trail', function (): void {
    $flat = DocTree::flatten();
    $groups = array_column($flat, 'group', 'slug');

    expect($groups['index'])->toBe('')
        ->and($groups['guides/labels'])->toBe('Guides')
        ->and($groups['guides/advanced/caching'])->toBe('Guides · Advanced');
});

it('falls back to the tail of the title when a folder shortens two docs to one nav label', function (): void {
    // Both are `Guides — …`, which shortens to `Guides` twice; the half after the cut is
    // the half that tells them apart.
    $advanced = DocTree::groups()[1]['groups'][0]['items'];

    expect(array_column($advanced, 'nav'))->toBe(['caching', 'queues'])
        ->and($advanced[0]['title'])->toBe('Guides — caching');
});

it('lets front matter set the title, the nav label and the order', function (): void {
    $guides = DocTree::groups()[1]['items'];

    expect($guides[0]['nav'])->toBe('Labels')
        ->and($guides[0]['title'])->toBe('Labels — front matter wins over the H1')
        ->and($guides[1]['slug'])->toBe('guides/getting-started')
        ->and(Docs::page('guides/labels')['html'])->not->toContain('nav:');
});

it('shortens an H1 into the nav label when front matter says nothing', function (): void {
    $guides = DocTree::groups()[1]['items'];

    expect($guides[1]['title'])->toBe('Getting started — the five minute version')
        ->and($guides[1]['nav'])->toBe('Getting started');
});

it('refuses a slug that climbs out of the docs root', function (): void {
    expect(Docs::page('../../composer.json'))->toBeNull()
        ->and(Docs::page('../../composer'))->toBeNull()
        ->and(Docs::page('../artisan'))->toBeNull()
        ->and(Docs::page('..'))->toBeNull()
        ->and(Docs::page('nope'))->toBeNull();
});
