<?php

declare(strict_types=1);

use Phattarachai\AiDocs\Support\Docs;

/**
 * On a Windows/Laragon host realpath() returns backslash paths; the root boundary
 * check must still match, or every page resolves to null and docs serve blank.
 * within() is exercised here directly so the behaviour is pinned on any OS.
 */
function within(string|false $absolute, string|false $root): ?string
{
    $method = new ReflectionMethod(Docs::class, 'within');

    return $method->invoke(null, $absolute, $root);
}

it('resolves a Windows realpath under a forward-slash root', function (): void {
    expect(within('C:\\laragon\\etax\\.ai\\documents\\user-workflow.md', 'C:\\laragon\\etax\\.ai\\documents'))
        ->toBe('user-workflow.md');

    expect(within('C:\\laragon\\etax\\.ai\\documents\\flows\\1-sap-fetch.md', 'C:\\laragon\\etax\\.ai\\documents'))
        ->toBe('flows/1-sap-fetch.md');
});

it('still resolves a POSIX realpath', function (): void {
    expect(within('/srv/app/.ai/documents/user-workflow.md', '/srv/app/.ai/documents'))
        ->toBe('user-workflow.md');
});

it('rejects a path outside the root or a failed realpath', function (): void {
    expect(within('C:\\laragon\\etax\\.ai\\other\\x.md', 'C:\\laragon\\etax\\.ai\\documents'))->toBeNull();
    expect(within(false, 'C:\\laragon\\etax\\.ai\\documents'))->toBeNull();
    expect(within('/srv/app/.ai/documents/a.md', false))->toBeNull();
});
