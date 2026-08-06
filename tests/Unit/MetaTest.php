<?php

declare(strict_types=1);

use Phattarachai\AiDocs\Support\Meta;
use Phattarachai\AiDocs\Support\Slug;

it('shortens a verbose heading into a nav label without breaking Thai', function (): void {
    // rtrim() with a multibyte charlist strips BYTES, which ate the tail off Thai titles.
    expect(Meta::shorten('Docs panel — .ai/documents/ rendered inside the app'))->toBe('Docs panel')
        ->and(Meta::shorten('Auth & dashboard (spec §1.0 — built)'))->toBe('Auth & dashboard')
        ->and(Meta::shorten('แดชบอร์ด — the dealer overview'))->toBe('แดชบอร์ด')
        ->and(Meta::shorten('Mail Log'))->toBe('Mail Log');
});

it('leaves a cut inside the first three characters alone', function (): void {
    expect(Meta::shorten('A — B'))->toBe('A — B')
        ->and(Meta::tail('A — B'))->toBe('');
});

it('takes the tail from the earliest cut, so a colliding folder still reads apart', function (): void {
    expect(Meta::tail('Admin panel — form conventions'))->toBe('form conventions')
        ->and(Meta::tail('Admin panel: tables — and their columns'))->toBe('tables — and their columns')
        ->and(Meta::tail('Auth & dashboard (spec §1.0 — built)'))->toBe('spec §1.0 — built')
        ->and(Meta::tail('แดชบอร์ด — ภาพรวมของดีลเลอร์'))->toBe('ภาพรวมของดีลเลอร์')
        ->and(Meta::tail('Mail Log'))->toBe('');
});

it('reproduces the heading slugs GitHub anchors were written against', function (): void {
    // GitHub's two quirks: punctuation is removed rather than replaced (so a spaced
    // em dash leaves TWO hyphens), and repeated slugs get a running suffix.
    $seen = [];

    expect(Slug::make('Activity Log — /admin/activity', $seen))->toBe('activity-log--adminactivity')
        ->and(Slug::make('Company sync', $seen))->toBe('company-sync')
        ->and(Slug::make('Company sync', $seen))->toBe('company-sync-1');
});

it('falls back to a generic id when a heading slugs to nothing', function (): void {
    $seen = [];

    expect(Slug::make('###', $seen))->toBe('section')
        ->and(Slug::make('แดชบอร์ด', $seen))->toBe('แดชบอร์ด');
});
