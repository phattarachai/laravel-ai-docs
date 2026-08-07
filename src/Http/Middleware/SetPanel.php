<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Phattarachai\AiDocs\AiDocs;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins the panel for the request before anything reads the tree, so the controller,
 * the markdown renderer and the media route all resolve against the same root.
 *
 * @see docs/internals.md — "The current panel is a static frame"
 */
final class SetPanel
{
    public function handle(Request $request, Closure $next, string $panel): Response
    {
        AiDocs::usePanel($panel);

        return $next($request);
    }
}
