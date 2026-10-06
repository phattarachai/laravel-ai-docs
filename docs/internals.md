# Internals — traps worth knowing before you change something

Notes for whoever maintains this package. Everything here is a bug that already happened once; the
code carries no comment explaining it, so this is the only record.

## The render cache keys on the pipeline file's own mtime

`Markdown::version()` returns `filemtime(__FILE__)`. Editing the pipeline therefore invalidates
every cached page automatically.

The obvious alternative — a hand-bumped `CACHE_VERSION` constant — was in place for exactly one
afternoon before it was forgotten after adding syntax highlighting, and the panel served stale HTML
that looked like the highlighter was broken. The cache key also carries the locale, because
callout headings and the two aria-labels are rendered server-side from `lang/{locale}/ui.php`.

## `Meta::shorten()` must never use `rtrim`

`rtrim($title, " \t—–-:(")` strips **bytes**, not characters. Byte `0x94` ends the em dash `—`
(`E2 80 94`) and also the Thai character `ด` (`E0 B8 94`), so a title ending in Thai came back as
invalid UTF-8 — and `json_encode` on the Inertia props then returned `false`, which surfaces as the
useless error "Not a valid Inertia response."

Use the anchored `preg_replace` with the `u` flag that is there now. A test pins the Thai case.

## Nested groups

`DocTree::groups()` returns a **recursive** shape — each group carries `items` (its own pages) and
`groups` (its subfolders) — so `docs/cart/prices/` renders inside *Cart* rather than beside it as a
sibling group labelled `Cart/prices`.

Two consequences that are easy to miss when adding a consumer:

- A folder holding only subfolders never appears in the page map, because nothing was ever filed
  under its path. `DocTree::directories()` walks every path back up so the intermediate level still
  exists — drop that and `ws2/quotation/` renders at the top level with no parent.
- Anything that wants a flat list of docs — the search index, the landing-page fallback — must go
  through `DocTree::flatten()`, which also composes the ` · `-joined group trail. Iterating
  `$groups[*]['items']` silently skips everything below the first level.

On the client, `locate()` returns the whole trail rather than the leaf group, because the sidebar has
to expand every ancestor of the current page, and the breadcrumb shows the full path.

## The current panel is a static frame, not a parameter

Every Support class resolves against "the current panel" through `AiDocs::root()` / `url()` /
`excluded()`. The alternative — threading a panel through `DocTree`, `Docs`, `SearchIndex`, `Links`
and the media route — touches every signature in the package to serve one feature, so the panel is a
static instead, pinned per request by the `SetPanel` middleware.

Reading another panel is therefore a frame: `AiDocs::within('tasks', fn () => …)`, which restores the
previous panel in a `finally`. Three consequences:

- Routes are registered **at boot** from config, so a panel layout cannot be switched on inside a
  test. `PanelsTestCase` exists for that reason; `config()->set('ai-docs.panels', …)` mid-test
  changes the trees without changing the routes, which is worse than not working.
- `AiDocsServiceProvider::register()` resets the static, or one test's panel leaks into the next.
- Both the render cache and the search cache carry `AiDocs::panelKey()`. Without it two panels
  holding a doc at the same relative path serve each other's HTML.

`Links` walks *every* panel when it resolves a target, current panel first and then deepest root
first. Splitting one root into panels must not turn a doc-to-doc link into a link off to the code
host, and a panel rooted at `.ai/tasks` has to win over one rooted at `.ai`.

Watch for this in the fixtures: `AiDocs::root()` returns a **realpath**, so a `../documents/x.md`
link is walked in resolved space. The test fixture folders are named after the links pointing at
them and sit side by side for exactly that reason.

## `Meta::tail()` exists because `shorten()` keeps the wrong half

`shorten()` cuts a title at its first ` — ` and keeps the part **before** it, which is right for a
title naming its own subject and wrong for a folder whose docs all share a prefix: five
`Admin panel — …` pages all list as *Admin panel*. `DocTree::disambiguate()` detects the collision
per folder and swaps in `Meta::tail()`, the half after the cut.

It runs **before** the sort, so the sidebar is alphabetical on the label the reader actually sees.
Front matter always wins — an explicit `nav:` is never rewritten, which is what makes a deliberate
duplicate possible.

## Never set mermaid's `fontFamily` or `fontSize`

Mermaid measures a label's width using the configured font *before* the real font paints, so every
node clipped its own text ("Issue RegistrationCc"). `mermaid-theme.js` deliberately sets neither;
the palette does not need them.

## A failed render is retried once when the source parses

`mermaid.render()` lazy-imports the diagram's chunk on first use, and in a Vite build that import
goes through Vite's preload helper, which also preloads the chunk's CSS dependencies. If one of those
`<link rel="stylesheet">` preloads fails, the helper rejects **that one** import — and remembers the
URL, so it never tries it again. The first diagram on the page fell back to its raw source while every
later one, and the same one after a scheme toggle, rendered fine.

The real-world trigger was a host that built its assets with `.env.production` (an absolute
`ASSET_URL` for another server) and served them locally: Blade's `<link>` used the local URL, the
preload list used the baked one, and only the CSS preload failed. Any flaky or blocked stylesheet does
the same.

So `draw()` treats a render failure as final only when `mermaid.parse(source, { suppressErrors: true })`
also fails; a source that parses gets one more render, which succeeds because the import is now
cached. Every failed attempt is logged with `console.warn` and its diagram id — the fallback alone said
nothing, which is why this took a fresh module realm to diagnose.

## The zoom button is a sibling of the diagram, not a child

`MermaidRenderer` emits `<div class="doc-wide"><button class="doc-zoom">…<div class="doc-mermaid">`.
Mermaid replaces `.doc-mermaid`'s children on every theme flip, so a button placed inside is eaten
on the first light/dark toggle.

## `DocProse` is memoized, and the click handler must stay off it

Mermaid draws into server-rendered HTML **after** mount, so those SVGs live in DOM that React does
not track. Any parent state change — opening the search palette, showing a toast, opening full
screen — re-created the `dangerouslySetInnerHTML` prop object, made React re-apply the markup, and
wiped every drawn diagram until a reload. The draw effect keys on slug and scheme, so it never
re-ran.

The fix is the memoized `DocProse` component taking only `html` and a stable ref, with the delegated
click handler on the wrapper `div`. **Do not move the handler back onto the `<article>`** — its
identity changes every render and defeats the memo, restoring the bug.

There is no JS test runner in this package, so nothing guards this.

## CommonMark renderers return `null` to defer

A `NodeRendererInterface` that returns `null` hands the node to the next renderer by priority. That
is how `MermaidRenderer` (priority 10) and `CodeRenderer` (priority 9) share `FencedCode`, and how
`CalloutRenderer` lets an ordinary blockquote fall through to core.

`HtmlElement` does **not** escape string contents — escaping is explicit via `Xml::escape`. That is
what lets the highlighter's HTML pass through, and why anything else interpolated into an element
must be escaped by hand.

## GitHub alert markers arrive split across nodes

CommonMark parses `[!WARNING]` into separate `Text` nodes (`[`, `!WARNING`, `]`). `Markdown::alert()`
accumulates literals until the matched marker length is consumed, then restores any surplus. A naive
"is the first child's literal the marker?" check finds nothing.

## Heading slugs follow GitHub's quirks on purpose

Punctuation is **removed, not replaced**, and hyphen runs are never collapsed — so a spaced em dash
leaves two hyphens (`Activity Log — /admin/activity` → `activity-log--adminactivity`). Thai combining
marks are kept. Docs in the wild already carry hand-written anchors built on these rules; "fixing"
the slugger breaks all of them at once.
