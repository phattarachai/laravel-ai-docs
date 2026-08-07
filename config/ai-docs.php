<?php

declare(strict_types=1);

/** @see README.md */
return [

    'enabled' => env('AI_DOCS_ENABLED', default: true),

    'path' => env('AI_DOCS_PATH', 'docs'),

    'domain' => env('AI_DOCS_DOMAIN'),

    'middleware' => ['web'],

    'redirect_guests_to' => env('AI_DOCS_LOGIN_ROUTE', 'login'),

    /*
     | Relative to the project root.
     */
    'root' => env('AI_DOCS_ROOT', '.ai/documents'),

    /*
     | Path prefixes, relative to `root`, matched by whole segment: `handbook` drops
     | the directory and keeps `handbook.md`.
     */
    'exclude' => [],

    /*
     | Several trees, each with its own URL, sidebar and search index, and a switcher in
     | the header. Leave empty for one panel built from `path` / `root` / `exclude` above.
     |
     | Each entry may set `path` (defaults to the key), `root`, `label` and `exclude`;
     | whatever it omits falls back to the top-level key. Route names gain the panel key —
     | `ai-docs.tasks.index` — while a single unnamed panel keeps `ai-docs.index`.
     |
     | Links resolve across panels, so a doc in one tree can link to a doc in another.
     |
     |     'panels' => [
     |         'docs'  => ['root' => '.ai/documents', 'label' => 'Documents'],
     |         'tasks' => ['root' => '.ai/tasks',     'label' => 'Tasks'],
     |     ],
     */
    'panels' => [],

    /*
     | `scroll` — natural size inside its own box. `wrap` — cells wrap into the column width.
     | Pure client-side styling; the rendered HTML is identical either way.
     */
    'tables' => env('AI_DOCS_TABLES', 'scroll'),

    /*
     | Base URL of a code host, or null to render out-of-tree links as plain text.
     */
    'source_link_base' => env('AI_DOCS_SOURCE_BASE'),

    'cache' => env('AI_DOCS_CACHE', default: true),

    'brand' => [
        'name' => env('AI_DOCS_BRAND', env('APP_NAME', 'Docs')),
        'accent' => env('AI_DOCS_ACCENT', '#3b82f6'),
        'url' => env('AI_DOCS_BRAND_URL', '/'),
        'logo' => null,
    ],

];
