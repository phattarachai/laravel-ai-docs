<?php

declare(strict_types=1);

afterEach(function (): void {
    foreach (['js', 'ts', 'mjs', 'mts'] as $extension) {
        @unlink(base_path("vite.config.{$extension}"));
    }
});

it('finds the @ai-docs alias in a vite.config.ts', function (): void {
    file_put_contents(base_path('vite.config.ts'), "alias: { '@ai-docs': 'vendor/…' }");

    $this->artisan('ai-docs:doctor')
        ->doesntExpectOutputToContain('Add a resolve.alias entry for @ai-docs');
});

it('reports a missing @ai-docs alias', function (): void {
    file_put_contents(base_path('vite.config.js'), 'export default {}');

    $this->artisan('ai-docs:doctor')
        ->expectsOutputToContain('Add a resolve.alias entry for @ai-docs')
        ->assertFailed();
});

it('asks for resolve.dedupe only when the package is a symlink', function (): void {
    $link = base_path('vendor/phattarachai/laravel-ai-docs');
    @mkdir(dirname($link), recursive: true);
    file_put_contents(base_path('vite.config.js'), "alias: { '@ai-docs': 'vendor/…' }");

    $this->artisan('ai-docs:doctor')->doesntExpectOutputToContain('Vite dedupe');

    symlink(dirname(__DIR__, 2), $link);

    try {
        $this->artisan('ai-docs:doctor')->expectsOutputToContain('Add resolve.dedupe');

        file_put_contents(base_path('vite.config.js'), "dedupe: ['mermaid'], alias: { '@ai-docs': 'vendor/…' }");

        $this->artisan('ai-docs:doctor')->doesntExpectOutputToContain('Add resolve.dedupe');
    } finally {
        unlink($link);
    }
});
