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

        return response()->file($absolute, ['Cache-Control' => 'private, max-age=600']);
    }
}
