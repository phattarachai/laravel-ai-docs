<?php

declare(strict_types=1);

use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\Markdown;

use function Pest\Laravel\actingAs;

const PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

it('serves an image beside its doc and defuses one pointing outside the root', function (): void {
    $file = AiDocs::root().'/guides/shot.png';
    file_put_contents($file, base64_decode(PIXEL));

    try {
        $html = Markdown::render("![Shot](shot.png)\n\n![Logo](../../public/logo.svg)\n", 'guides')['html'];

        expect($html)->toContain('src="/docs/_media/guides/shot.png"')
            ->toContain('<span class="doc-ext">Logo</span>');

        actingAs(adUser())->get('/docs/_media/guides/shot.png')->assertOk();
    } finally {
        @unlink($file);
    }
});

it('refuses a media request that is not an image or climbs out of the root', function (): void {
    $outside = dirname((string) realpath(AiDocs::root())).'/outside.png';
    file_put_contents($outside, base64_decode(PIXEL));

    try {
        // The file is real and is an image — only the root containment check can reject it.
        expect(is_file($outside))->toBeTrue();

        actingAs(adUser())
            ->get('/docs/_media/'.urlencode('../outside.png'))
            ->assertNotFound();

        actingAs(adUser())
            ->get('/docs/_media/index.md')
            ->assertNotFound();
    } finally {
        @unlink($outside);
    }
});

it('never serves media out of an excluded directory', function (): void {
    $file = AiDocs::root().'/private/shot.png';
    file_put_contents($file, base64_decode(PIXEL));

    try {
        actingAs(adUser())->get('/docs/_media/private/shot.png')->assertNotFound();
    } finally {
        @unlink($file);
    }
});
