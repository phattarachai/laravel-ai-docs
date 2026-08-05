<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Phattarachai\AiDocs\AiDocs;
use Symfony\Component\HttpFoundation\Response;

/** @see README.md */
final class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (AiDocs::check($request)) {
            return $next($request);
        }

        $login = $this->loginUrl();

        if ($request->user() === null && ! $request->expectsJson() && $login !== null) {
            return redirect()->guest($login);
        }

        abort(403);
    }

    private function loginUrl(): ?string
    {
        $target = config('ai-docs.redirect_guests_to');

        if (! is_string($target) || $target === '') {
            return null;
        }

        if (Route::has($target)) {
            return route($target);
        }

        return str_contains($target, '/') ? url($target) : null;
    }
}
