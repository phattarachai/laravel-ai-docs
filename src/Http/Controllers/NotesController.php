<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Phattarachai\AiDocs\AiDocs;
use Phattarachai\AiDocs\Http\Requests\PushNoteRequest;
use Phattarachai\AiDocs\Support\Docs;
use Phattarachai\AiDocs\Support\DocTree;
use Phattarachai\AiDocs\Support\Meta;
use Phattarachai\AiDocs\Support\NotePath;
use Phattarachai\AiDocs\Support\NoteStore;

/**
 * The token API behind `ai-docs:push` and `ai-docs:pull`.
 *
 * @phpstan-import-type Panel from AiDocs
 *
 * @see README.md — "Notes"
 */
final class NotesController
{
    public function index(Request $request): JsonResponse
    {
        $panel = $this->panel($request->query('panel'));

        $notes = AiDocs::within($panel['key'], fn (): array => array_map(fn (array $doc): array => [
            'slug' => $doc['slug'],
            'title' => $doc['title'],
            'group' => $doc['group'],
            'updated' => Meta::updated((string) Docs::file($doc['slug'])),
            'author' => Meta::author((string) Docs::file($doc['slug'])),
            'url' => $this->url($panel, $doc['slug']),
        ], DocTree::flatten()));

        return response()->json(['panel' => $panel['key'], 'notes' => $notes]);
    }

    public function store(PushNoteRequest $request): JsonResponse
    {
        $panel = $this->panel($request->validated('panel'));
        $written = NoteStore::put($panel, $request->pushed(), $request->validated('author'), $request->prune());
        $slugs = AiDocs::within($panel['key'], fn (): array => array_map(DocTree::slug(...), $written));

        return response()->json([
            'panel' => $panel['key'],
            'files' => count($request->pushed()),
            'pages' => array_map(fn (string $slug): array => ['slug' => $slug, 'url' => $this->url($panel, $slug)], $slugs),
        ]);
    }

    public function show(Request $request, string $path): JsonResponse
    {
        $panel = $this->panel($request->query('panel'));
        $files = NoteStore::get($panel, $this->notePath($path));

        abort_if($files === [], 404);

        return response()->json([
            'panel' => $panel['key'],
            'files' => array_map(
                fn (string $file, string $contents): array => ['path' => $file, 'content' => base64_encode($contents)],
                array_keys($files),
                $files,
            ),
        ]);
    }

    public function destroy(Request $request, string $path): JsonResponse
    {
        $panel = $this->panel($request->query('panel'));

        abort_unless(NoteStore::delete($panel, $this->notePath($path)), 404);

        return response()->json(['panel' => $panel['key'], 'deleted' => $path]);
    }

    /**
     * @return Panel
     */
    private function panel(mixed $key): array
    {
        $panel = AiDocs::notePanel(is_string($key) && $key !== '' ? $key : null);

        abort_if($panel === null, 404, 'No note panel by that key: set `notes => true` on a panel.');

        return $panel;
    }

    private function notePath(string $path): string
    {
        $note = NotePath::note($path);

        abort_if($note === null, 404);

        return $note;
    }

    /**
     * @param  Panel  $panel
     */
    private function url(array $panel, string $slug): string
    {
        return url("{$panel['path']}/{$slug}");
    }
}
