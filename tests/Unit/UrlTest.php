<?php

declare(strict_types=1);

use Illuminate\Support\Facades\URL;
use Phattarachai\AiDocs\AiDocs;

it('folds a subfolder base path into doc urls', function (): void {
    URL::forceRootUrl('http://localhost/etax/public');

    expect(AiDocs::url())->toBe('/etax/public/docs');
    expect(AiDocs::url('guides/labels'))->toBe('/etax/public/docs/guides/labels');
});

it('stays at the domain root when the app is not in a subfolder', function (): void {
    URL::forceRootUrl('http://localhost');

    expect(AiDocs::url())->toBe('/docs');
    expect(AiDocs::url('guides/labels'))->toBe('/docs/guides/labels');
});

it('sends the brand link back to the app root, not the domain root', function (): void {
    URL::forceRootUrl('http://localhost/etax/public');

    expect(AiDocs::brand()['url'])->toBe('/etax/public');

    URL::forceRootUrl('http://localhost');

    expect(AiDocs::brand()['url'])->toBe('/');
});

it('folds a root-relative logo into the subfolder but leaves an absolute one alone', function (): void {
    URL::forceRootUrl('http://localhost/etax/public');

    config()->set('ai-docs.brand.logo', '/img/mark.svg');
    expect(AiDocs::brand()['logo'])->toBe('/etax/public/img/mark.svg');

    config()->set('ai-docs.brand.logo', 'https://cdn.test/mark.svg');
    expect(AiDocs::brand()['logo'])->toBe('https://cdn.test/mark.svg');

    config()->set('ai-docs.brand.logo', 'data:image/svg+xml;base64,PHN2Zy8+');
    expect(AiDocs::brand()['logo'])->toBe('data:image/svg+xml;base64,PHN2Zy8+');
});
