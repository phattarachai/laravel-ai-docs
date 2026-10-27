<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** @see README.md — "Notes" */
final class AuthenticateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->accepts((string) $request->bearerToken()), 401);

        return $next($request);
    }

    private function accepts(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $hash = hash('sha256', $token);

        foreach ((array) config('ai-docs.api.tokens', []) as $accepted) {
            if (is_string($accepted) && hash_equals(mb_strtolower(trim($accepted)), $hash)) {
                return true;
            }
        }

        return false;
    }
}
