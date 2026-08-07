<?php

declare(strict_types=1);

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('sends a guest to the login screen and remembers where they were going', function (): void {
    get(route('ai-docs.index'))->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('ai-docs.index'));
});

it('renders the page for a signed-in reader', function (): void {
    actingAs(adUser())
        ->get(route('ai-docs.index', ['path' => 'guides/getting-started']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AiDocs')
            ->where('page.slug', 'guides/getting-started')
            ->has('groups')
            ->has('strings'));
});

it('offers no switcher when there is only one panel', function (): void {
    actingAs(adUser())
        ->get(route('ai-docs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('panels', []));
});

it('falls back to the index page when no slug is given', function (): void {
    actingAs(adUser())
        ->get(route('ai-docs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('page.slug', 'index'));
});

it('forbids a guest XHR rather than redirecting it', function (): void {
    get(route('ai-docs.index'), ['Accept' => 'application/json'])->assertForbidden();
});

it('forbids a guest outright when no login route is configured', function (): void {
    config()->set('ai-docs.redirect_guests_to');

    get(route('ai-docs.index'))->assertForbidden();
});

it('keeps the search index behind the gate', function (): void {
    get(route('ai-docs.search'))->assertRedirect(route('login'));

    get(route('ai-docs.search'), ['Accept' => 'application/json'])->assertForbidden();
});
