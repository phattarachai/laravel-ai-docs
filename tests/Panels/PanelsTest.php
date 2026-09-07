<?php

declare(strict_types=1);

use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\Docs;
use Phattarachai\AiDocs\Support\DocTree;

use function Pest\Laravel\actingAs;

it('serves each panel from its own path, over its own tree', function (): void {
    actingAs(adUser())
        ->get(route('ai-docs.docs.index', ['path' => 'guides/labels']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('page.path', '.ai/documents/guides/labels.md'));

    actingAs(adUser())
        ->get(route('ai-docs.tasks.index', ['path' => 'cycle-1/42-thing/todo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('page.path', '.ai/tasks/cycle-1/42-thing/todo.md'));
});

it('cannot read one panel through another', function (): void {
    actingAs(adUser())
        ->get(route('ai-docs.docs.index', ['path' => 'cycle-1/index']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('page', null));
});

it('gives the header a switcher that marks where you are', function (): void {
    actingAs(adUser())
        ->get(route('ai-docs.tasks.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('panels', [
            ['key' => 'docs', 'label' => 'Documents', 'url' => '/docs', 'current' => false],
            ['key' => 'tasks', 'label' => 'Tasks', 'url' => '/tasks', 'current' => true],
        ]));
});

it('links from one panel into another instead of off to the code host', function (): void {
    config()->set('ai-docs.source_link_base', 'https://example.test/blob/main/');

    expect(AiDocs::within('tasks', fn (): string => Docs::page('links')['html']))
        ->toContain('href="/docs/guides/labels"')
        ->not->toContain('example.test');
});

it('lands a folder link on the page the sidebar lists first under it', function (): void {
    $html = AiDocs::within('tasks', fn (): string => Docs::page('links')['html']);

    // `order: 1` on requirement.md, so it wins over todo.md.
    expect($html)->toContain('href="/tasks/cycle-1/42-thing/requirement"')
        // index.md wins when nothing carries an explicit order.
        ->toContain('href="/tasks/cycle-1/index"')
        // A folder with no page of its own hands off to the first one below it.
        ->toContain('href="/tasks/holder/child/note"');
});

it('serves a linked screenshot through the panel rather than the code host', function (): void {
    config()->set('ai-docs.source_link_base', 'https://example.test/blob/main/');

    expect(AiDocs::within('tasks', fn (): string => Docs::page('links')['html']))
        ->toContain('href="/tasks/_media/cycle-1/42-thing/asset.svg"');
});

it('leaves a link to an excluded page alone', function (): void {
    expect(AiDocs::within('tasks', fn (): string => Docs::page('links')['html']))
        ->not->toContain('/docs/private/secret');
});

it('serves media out of the panel that holds it', function (): void {
    actingAs(adUser())
        ->get('/tasks/_media/cycle-1/42-thing/asset.svg')
        ->assertOk()
        ->assertHeader('content-type', 'image/svg+xml');

    actingAs(adUser())->get('/docs/_media/cycle-1/42-thing/asset.svg')->assertNotFound();
});

it('keeps each panel search index to its own pages', function (): void {
    $slugs = fn (string $url): array => array_column(actingAs(adUser())->getJson($url)->json(), 'slug');

    expect($slugs('/tasks/_search.json'))->toContain('cycle-1/42-thing/todo')
        ->not->toContain('guides/labels')
        ->and($slugs('/docs/_search.json'))->toContain('guides/labels')
        ->not->toContain('cycle-1/42-thing/todo');
});

it('groups a folder whose name is all digits instead of tripping over the integer key', function (): void {
    $groups = AiDocs::within('tasks', fn (): array => DocTree::groups());

    expect(array_column($groups, 'key'))->toContain('2609');

    actingAs(adUser())
        ->get(route('ai-docs.tasks.index', ['path' => '2609/31-migrate/todo']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('page.path', '.ai/tasks/2609/31-migrate/todo.md'));
});
