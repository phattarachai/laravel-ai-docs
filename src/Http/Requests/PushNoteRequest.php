<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Phattarachai\AiDocs\Support\NotePath;

/** @see README.md — "Notes" */
final class PushNoteRequest extends FormRequest
{
    private const int MAX_FILES = 500;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'panel' => ['nullable', 'string', 'max:100'],
            'author' => ['nullable', 'string', 'max:100'],
            'prune' => ['nullable', 'string', 'max:255', $this->notePath(...)],
            'files' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
            'files.*.path' => ['required', 'string', 'max:255', 'distinct', $this->filePath(...)],
            'files.*.content' => ['present', 'string', $this->base64(...)],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty() && strlen(implode('', $this->pushed())) > (int) config('ai-docs.api.max_kb', 20480) * 1024) {
                $validator->errors()->add('files', 'The push is larger than ai-docs.api.max_kb.');
            }
        }];
    }

    /**
     * The decoded files, keyed by their path relative to the panel root.
     *
     * @return array<string, string>
     */
    public function pushed(): array
    {
        $files = [];

        foreach ((array) $this->input('files', []) as $file) {
            $files[(string) NotePath::file((string) $file['path'])] = (string) base64_decode((string) $file['content'], strict: true);
        }

        return $files;
    }

    public function prune(): ?string
    {
        $prune = $this->validated('prune');

        return is_string($prune) ? NotePath::note($prune) : null;
    }

    private function notePath(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || NotePath::note($value) === null) {
            $fail("The {$attribute} is not a note path.");
        }
    }

    private function base64(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || base64_decode($value, strict: true) === false) {
            $fail("The {$attribute} must be base64.");
        }
    }

    private function filePath(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || NotePath::file($value) === null) {
            $fail("The {$attribute} must be a markdown or image path inside the panel.");
        }
    }
}
