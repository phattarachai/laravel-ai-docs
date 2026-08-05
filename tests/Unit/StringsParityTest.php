<?php

declare(strict_types=1);

/*
 | `callout.*` and `anchor.label` are rendered server-side into the markdown, so
 | they live in the lang files only and never reach the JS module.
 */
const SERVER_ONLY = ['anchor.label'];

it('keeps the JS string defaults and the lang files in key parity', function (): void {
    $source = (string) file_get_contents(__DIR__.'/../../resources/js/ai-docs/strings.js');
    $start = (int) mb_strpos($source, 'DEFAULT_STRINGS');
    $body = mb_substr($source, $start, (int) mb_strpos($source, 'StringsContext') - $start);

    preg_match_all("/'((?:[a-z][A-Za-z]*)(?:\.[A-Za-z_]+)+)':/", $body, $matches);
    $jsKeys = array_values(array_unique($matches[1]));

    $en = array_keys(require __DIR__.'/../../lang/en/ui.php');
    $th = array_keys(require __DIR__.'/../../lang/th/ui.php');

    $serverSide = array_values(array_filter(
        array_diff($en, $jsKeys),
        fn (string $key): bool => ! str_starts_with($key, 'callout.') && ! in_array($key, SERVER_ONLY, strict: true),
    ));

    expect($jsKeys)->not->toBeEmpty()
        ->and(array_diff($jsKeys, $en))->toBe([], 'keys in strings.js but missing from lang/en/ui.php')
        ->and($serverSide)->toBe([], 'keys in lang/en/ui.php with no matching string in strings.js')
        ->and(array_diff($en, $th))->toBe([], 'keys missing from lang/th/ui.php')
        ->and(array_diff($th, $en))->toBe([], 'keys in lang/th/ui.php that lang/en/ui.php lacks');
});

it('leaves no Thai copy inside the portable JS module', function (): void {
    $files = glob(__DIR__.'/../../resources/js/ai-docs/*') ?: [];

    foreach ($files as $file) {
        expect(preg_match('/[\x{0E00}-\x{0E7F}]/u', (string) file_get_contents((string) $file)))
            ->toBe(0, basename((string) $file).' still contains Thai copy — it belongs in lang/th/ui.php');
    }
});
