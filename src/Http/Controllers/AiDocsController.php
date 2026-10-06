<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Support\Docs;
use Phattarachai\AiDocs\Support\DocTree;
use Phattarachai\AiDocs\Support\SearchIndex;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** @see README.md */
final class AiDocsController
{
    /**
     * Sent explicitly: under `nosniff` a guessed `text/xml` would leave an SVG unrendered.
     *
     * @var array<string, string>
     */
    private const array TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
    ];

    public function show(?string $path = null): Response
    {
        $slug = $path ?? Docs::first();

        return Inertia::render('AiDocs', [
            'base' => AiDocs::url(),
            'brand' => AiDocs::brand(),
            'groups' => DocTree::groups(),
            'page' => $slug === null ? null : Docs::page($slug),
            'panels' => AiDocs::switcher(),
            'strings' => trans('ai-docs::ui'),
        ]);
    }

    public function search(): JsonResponse
    {
        return response()->json(SearchIndex::build());
    }

    public function media(string $path): BinaryFileResponse
    {
        $absolute = Docs::media($path);

        abort_if($absolute === null, 404);

        $headers = [
            'Cache-Control' => 'private, max-age=600',
            'Content-Type' => self::TYPES[mb_strtolower(pathinfo($absolute, PATHINFO_EXTENSION))],
            'X-Content-Type-Options' => 'nosniff',
        ];

        // Same-origin, so an SVG opened directly would run any script inside it. An
        // `<img>` never runs one either way; this covers the address bar.
        if (str_ends_with(mb_strtolower($absolute), '.svg')) {
            $headers['Content-Security-Policy'] = "default-src 'none'; style-src 'unsafe-inline'; img-src data:; font-src data:";
        }

        return response()->file($absolute, $headers);
    }
}
