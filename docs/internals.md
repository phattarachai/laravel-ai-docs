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

## Never set mermaid's `fontFamily` or `fontSize`

Mermaid measures a label's width using the configured font *before* the real font paints, so every
node clipped its own text ("Issue RegistrationCc"). `mermaid-theme.js` deliberately sets neither;
the palette does not need them.

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
