<?php

declare(strict_types=1);

use Phattarachai\AiDocs\Support\SearchIndex;

use function Pest\Laravel\actingAs;

it('indexes one entry per servable doc, each split into sections', function (): void {
    $index = SearchIndex::build();

    expect(array_column($index, 'slug'))->toEqualCanonicalizing([
        'index',
        'links',
        'diagrams',
        'private',
        'guides/getting-started',
        'guides/labels',
        'guides/advanced/caching',
        'guides/advanced/queues',
    ]);

    $nested = collect($index)->firstWhere('slug', 'guides/advanced/queues');
    $started = collect($index)->firstWhere('slug', 'guides/getting-started');

    expect($nested['group'])->toBe('Guides · Advanced')
        ->and($started['group'])->toBe('Guides')
        ->and($started['sections'])->toHaveCount(3)
        ->and($started['sections'][0])->toHaveKeys(['id', 'heading', 'level', 'text'])
        ->and($started['sections'][0]['heading'])->toBe('Getting started — the five minute version')
        ->and($started['sections'][0]['level'])->toBe(1);
});

it('carries the body text of a section into its entry', function (): void {
    $root = collect(SearchIndex::build())->firstWhere('slug', 'index');

    expect($root['sections'][0]['text'])->toContain('tiny documentation tree');
});

it('hands the index to a signed-in reader over the endpoint', function (): void {
    $index = actingAs(adUser())->get(route('ai-docs.search'))->assertOk()->json();

    expect($index)->toHaveCount(8)
        ->and($index[0])->toHaveKeys(['slug', 'title', 'group', 'sections']);
});
