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
