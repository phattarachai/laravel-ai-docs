<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The server's token API, called by `ai-docs:push` and `ai-docs:pull`.
 *
 * @see README.md — "Notes"
 */
final class NotesRemote
{
    private const int TIMEOUT_SECONDS = 120;

    /**
     * @return array{panel: string, notes: list<array{slug: string, title: string, group: string, updated: string|null, author: string|null, url: string}>}
     */
    public function list(?string $panel): array
    {
        return $this->request()->get('notes', array_filter(['panel' => $panel]))->throw()->json();
    }

    /**
     * @param  array<string, string>  $files  contents keyed by path relative to the panel root
     * @return array{panel: string, files: int, pages: list<array{slug: string, url: string}>}
     */
    public function push(array $files, ?string $panel, ?string $author, ?string $prune): array
    {
        return $this->request()->post('notes', [
            ...array_filter(['panel' => $panel, 'author' => $author, 'prune' => $prune]),
            'files' => array_map(
                fn (string $path, string $contents): array => ['path' => $path, 'content' => base64_encode($contents)],
                array_keys($files),
                $files,
            ),
        ])->throw()->json();
    }

    /**
     * @return array<string, string> contents keyed by path relative to the panel root
     */
    public function pull(string $path, ?string $panel): array
    {
        $files = [];

        foreach ($this->request()->get('notes/'.$this->encode($path), array_filter(['panel' => $panel]))->throw()->json('files') as $file) {
            $files[(string) $file['path']] = (string) base64_decode((string) $file['content'], strict: true);
        }

        return $files;
    }

    public function delete(string $path, ?string $panel): void
    {
        $query = $panel === null ? '' : '?'.http_build_query(['panel' => $panel]);

        $this->request()->delete('notes/'.$this->encode($path).$query)->throw();
    }

    public static function reason(RequestException $exception): string
    {
        $response = $exception->response;
        $errors = (array) $response->json('errors', []);
        $detail = $errors === [] ? (string) $response->json('message', '') : implode(' ', array_merge(...array_values(array_map(fn (mixed $messages): array => (array) $messages, $errors))));

        return trim("The server answered {$response->status()}. {$detail}");
    }

    private function encode(string $path): string
    {
        return implode('/', array_map(rawurlencode(...), explode('/', $path)));
    }

    private function request(): PendingRequest
    {
        $url = rtrim((string) config('ai-docs.remote.url'), '/');
        $token = (string) config('ai-docs.remote.token');

        throw_if($url === '', RuntimeException::class, 'AI_DOCS_REMOTE_URL is not set: the URL of the app the notes live on.');
        throw_if($token === '', RuntimeException::class, 'AI_DOCS_REMOTE_TOKEN is not set: run `php artisan ai-docs:token --write`.');

        $path = trim((string) config('ai-docs.path', 'docs'), '/');

        return Http::baseUrl("{$url}/{$path}/_api/v1")
            ->withToken($token)
            ->acceptJson()
            ->timeout(self::TIMEOUT_SECONDS);
    }
}
