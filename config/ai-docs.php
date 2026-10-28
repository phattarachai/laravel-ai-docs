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
     | `notes => true` makes a panel Claude pushes pages to over the token API below,
     | with no commit or deploy. Its `root` defaults to `storage/app/ai-docs/<key>` on
     | the server, and `local` (default `.ai/<key>`) is where `ai-docs:pull` writes the
     | working copy in a checkout.
     |
     |     'panels' => [
     |         'docs'  => ['root' => '.ai/documents', 'label' => 'Documents'],
     |         'tasks' => ['root' => '.ai/tasks',     'label' => 'Tasks'],
     |         'notes' => ['label' => 'Notes', 'notes' => true],
     |     ],
     */
    'panels' => [],

    /*
     | `scroll` — natural size inside its own box. `wrap` — cells wrap into the column width.
     | Pure client-side styling; the rendered HTML is identical either way.
     */
    'tables' => env('AI_DOCS_TABLES', 'scroll'),

    /*
     | `light`, `soft`, `nord` or `dark`: the theme for a reader who hasn't picked one in
     | the header. Null follows their OS light/dark setting. Their own pick always wins.
     */
    'theme' => env('AI_DOCS_THEME'),

    /*
     | Base URL of a code host, or null to render out-of-tree links as plain text.
     */
    'source_link_base' => env('AI_DOCS_SOURCE_BASE'),

    'cache' => env('AI_DOCS_CACHE', default: true),

    /*
     | The token API that `ai-docs:push` and `ai-docs:pull` call, mounted at
     | `<path>/_api/v1`. `tokens` are sha256 hashes of the accepted bearer tokens, from a
     | comma list; `php artisan ai-docs:token --write` makes one. Empty refuses every
     | request. To rotate, append the new hash, switch the client, then drop the old one.
     */
    'api' => [
        'tokens' => array_values(array_filter(array_map(trim(...), explode(',', (string) env('AI_DOCS_TOKEN_HASHES', ''))))),
        'throttle' => env('AI_DOCS_THROTTLE', '60,1'),
        'max_kb' => (int) env('AI_DOCS_MAX_PUSH_KB', 20480),
    ],

    /*
     | Used by the ai-docs:* commands in a developer's checkout: the app to push to,
     | the plaintext token, and the name shown beside a pushed page's date.
     */
    'remote' => [
        'url' => env('AI_DOCS_REMOTE_URL'),
        'token' => env('AI_DOCS_REMOTE_TOKEN'),
        'author' => env('AI_DOCS_AUTHOR', 'Claude'),
    ],

    'brand' => [
        'name' => env('AI_DOCS_BRAND', env('APP_NAME', 'Docs')),
        'accent' => env('AI_DOCS_ACCENT', '#3b82f6'),
        'url' => env('AI_DOCS_BRAND_URL', '/'),
        'logo' => null,
    ],

];
