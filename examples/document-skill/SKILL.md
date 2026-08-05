---
name: document
description: "Write or update feature/domain docs under `.ai/documents/`, keeping CLAUDE.md a thin index — rationale lives in the docs, docblocks carry tags and a bare pointer, never sentences. Triggers on \"document this\", \"add a doc\", \"this doc is stale\", or a code change that outdates its doc."
user-invocable: true
argument-hint: "<topic, doc path, or \"all\">"
---

# Document

Living documentation for **features, domains, and core business logic** lives in `.ai/documents/` — the folder
`laravel-ai-docs` renders at `/docs`. Adjust the path below if `ai-docs.root` points somewhere else.

## Hard rule: no documentation in docblocks. None.

**A docblock may not contain a sentence.** Not a paragraph, not a "brief" one-liner, not a parenthetical aside. Why a
decision was made, what breaks if you undo it, the measurement behind a constant, the API's lies, the bug that produced
the guard — all of it goes in `.ai/documents/`, and nowhere else.

This is absolute because the softer version ("avoid *narrative* docblocks") kept being read as permission for a short
one. It isn't. The test is mechanical:

> **If it reads as a sentence, delete it. If it's a tag or a path, keep it.**

**Keep — the entire allowlist:**

- Type/tooling annotations that IDEs and static analysis consume: `@param`, `@return`, `@var`, `@property`, `@throws`,
  `@template`, `@deprecated`, generics, array shapes. Tags only — a `@param` whose description turns into an
  explanation is prose wearing a tag.
- A bare doc pointer: `see .ai/documents/<domain>/<file>.md`. It carries no fact that can go stale.

**Everything else is prohibited**, including a docblock that merely restates the doc. Two copies of one fact drift, and
the code copy is the one nobody updates — so you can't tell which is current without reading the implementation, the
exact thing the doc existed to save you from.

**Clean up what you find.** When working in a domain, strip stray prose from the files that domain's doc lists under
*Related files* — harvest any fact worth keeping into the doc, then delete the comment. Scope it to that domain.

## Structure — as flat as the project allows

- **A domain folder needs at least two docs.** One doc alone in a folder is scatter. Put it flat at the root, and
  create the folder only when a second doc joins it. When a folder drops back to one doc, flatten it in the same pass.
- **Cross-cutting docs live flat** — design system, auth, testing, infrastructure. They apply across domains.
- Prefer few folders. Two or three domain folders plus a handful of root files is the target shape.

## What goes in a doc

- **Purpose** — what this feature does and why.
- **Flow** — the sequence of what happens (request → handler → side effects → response). Keep it skimmable.
- **Related APIs / endpoints** — routes or method signatures the feature exposes or consumes.
- **Related files** — link the files that implement it so the reader can jump in.

Those four are the *ceiling*, not a checklist. A doc with nothing to say about flow omits the Flow section.

## Writing for the docs panel

Docs are read as markdown *and* served as a browsable site. Everything here is optional and degrades to plain markdown.

**Front matter — only when the defaults are wrong.** The nav label is the H1 cut at its first ` — `, ` – `, ` (` or
`: `, so `# Invoicing — generation, PDFs and tax lines` already lists as *Invoicing*.

```yaml
---
title: Invoicing — generation, PDFs and tax lines   # page title + browser tab; defaults to the H1
nav: Invoicing                                      # sidebar label; defaults to the shortened title
order: 10                                           # sort within the folder; unset sorts as 500
---
```

Within a group, pages sort by `order`, then `index.md` first, then nav label. The common reason to add a key is a
collision — three docs titled `Billing — …` all shorten to *Billing*. Check the siblings before deciding you need one.

**Callouts use GitHub's alert syntax**, so the doc renders the same on github.com:

```markdown
> [!WARNING]
> Deleting a plan cascades to every active subscription on it.
```

`NOTE` · `TIP` · `IMPORTANT` · `WARNING` · `CAUTION`. A plain `>` quote stays a plain quote — reach for an alert when a
reader who skims past it will break something, not for ordinary emphasis.

**Mermaid diagrams** go in a ` ```mermaid ` fence and are the right tool for a flow with branches; an ascii sketch is
still better for a two-step sequence. Colour carries meaning, not decoration: rhombus decision nodes are amber
automatically, and four classes are available where a node's role matters.

```markdown
    A[Order placed] --> B{Payment cleared?}
    B -->|yes| C[Fulfil]
    B -->|no| D[Cancel]
    class C ok
    class D bad
```

`decision` · `ok` · `bad` · `actor`, applied with `class Node ok` or `Node:::ok`. Leave ordinary process nodes
unclassed. `mermaid` must be an npm dependency of the host app; without it the fence degrades to plain text.

**Code fences carry a language** (```php, ```bash, ```json). Highlighting is server-side; an untagged fence renders as
plain text.

**Images sit beside the doc**, referenced relatively (`![Panel](panel.png)`), and are streamed through the docs gate —
nothing goes in `public/`. An image outside the docs tree renders as plain text, as does a relative link pointing
outside it, unless `source_link_base` is configured.

## Concise by default, 500 lines hard

**Write what the reader needs and stop.** 500 lines is a hard ceiling, not a target — most docs belong well under 100.

- **No padding sections.** No Overview that repeats the Purpose, no Summary/Conclusion that repeats the body, no table
  of contents, no "Notes" bucket, no section that exists only because the template had it.
- **No exhaustive enumeration.** Don't list every field, every branch, every test. Name the rule and the two cases that
  break it; the reader has the code for the rest.
- **One sentence per idea.** Don't restate a point in a bullet list after making it in prose.

Long because it's padded → cut it. Long because the domain is large → **a second domain is hiding inside the first**,
so extract the sub-domain rather than compressing prose or deleting detail:

- Name the extracted doc for the thing it owns, not for the leftovers (`webhooks-retry.md`, not `webhooks-part-2.md`).
  If you can't name it, you haven't found a real seam — look again.
- Cross-link both ways: the parent names the child where the topic hands off, the child names its parent up top.
- Add the child to `.ai/documents/index.md` in the same pass, with its own one-line description.

Trimming to fit loses the rationale the doc exists to carry. Split instead.

## History belongs to the tracker, not the doc

**A doc describes the system as it is now.** No `## History`, `## Changelog`, `## Previously`, `## Migration notes` or
a running log of what each release changed. That content is real — it just lives wherever the work is tracked (the
issue, the PR, the task file).

- **Link, don't narrate.** Where the history matters, the doc carries a one-line pointer to the change record.
- **Superseded behaviour is deleted, not archived.** If the doc explains how something works today, the old way is
  noise.
- The exception is a fact the *current* system still depends on — a legacy format still being parsed, a column still
  populated for back-compat. That's present behaviour with an unfortunate origin: document it as current, and say what
  depends on it.

## Discoverability (do this every time)

1. **Maintain `.ai/documents/index.md`** — the map of all docs, grouped by domain, one line each, and the panel's
   landing page. Whenever you create, rename, move, or delete a doc, update it in the same pass: add/remove the bullet
   and create the domain `## heading` if the folder is new. Create the file if it doesn't exist.
2. **Ensure `CLAUDE.md` points to the index.** If the link is missing, add a short "Domain Documentation" section
   pointing there. `CLAUDE.md` points to the index only — never enumerate individual docs there.

## `CLAUDE.md` is an index, not a doc

`CLAUDE.md` is loaded **in full, every session, forever**. A doc is loaded when someone needs it.

> **If a fact has a doc, `CLAUDE.md` gets a pointer to it, not the fact.**

- **No section may exist in both.** The doc wins: move the detail there, leave `CLAUDE.md` a line naming the doc.
- **A `CLAUDE.md` section past ~5 lines of detail is a doc trying to be born.** Extract it in the same pass that grew
  it.
- **What legitimately stays**: the one-paragraph description of what the app is, hard guardrails someone must see
  *without* opening a file, and pointers.
- **Don't restate injected guidelines.** A generated block inside `CLAUDE.md` already states the installed packages,
  versions, conventions and commands. If it can be derived from `composer.json`, `package.json`, or the framework's own
  conventions, cut it — what survives is only what *contradicts* the default.

If the project has a rules tier (`.ai/rules/*.md`, scoped by `paths:` globs and loaded whenever a matching path is
touched), the split is mechanical:

> **Imperative and scoped to a path → rule. Explains, enumerates, or justifies → doc.**

No sentence may exist in both; the rule wins for imperatives, the doc keeps the mechanism. Link them to each other.
Rules load far more often than docs, so they stay the shortest tier.

## Verify before you finish (every run)

These break silently — nothing fails a test or a lint, so check them yourself:

1. No doc over 500 lines (`wc -l .ai/documents/**/*.md`) and no history heading
   (`grep -rniE '^#+ *(history|changelog|previously|migration notes)' .ai/documents/`).
2. Every doc you added or renamed appears in `index.md`, and no index entry points at a file that no longer exists.
3. Every inbound pointer still resolves after a rename — sweep the **whole repo**
   (`git grep -l '\.ai/documents/OLD-PATH\.md'`). Most pointers live in `app/` and `resources/` docblocks.
4. A pointer naming a section (`… — "Retry backoff"`) still finds that heading. A split keeps the path valid while
   moving the heading, so this one never looks broken.

## Rules

- **No raw code.** Describe behaviour, not implementation. Method and class names are fine; full method bodies are not.
- **Always link related files** with relative paths, so the reader never has to guess where the code lives.
- **One feature = one file** — up to the 500-line ceiling, past which the feature was really two.
- **No horizontal-rule (`---`) section separators.** `##` headings already break sections.
- **Write in English by default.** Keep a file consistent with itself; don't mix languages mid-file.
- Use `git mv` when relocating a doc, so blame and `--follow` survive.

## Changing a feature that already has a doc

**The doc is part of the feature.** A code change that outdates its doc is an incomplete change — the doc is what the
next session reads *instead of* the code, so a stale one is worse than none: it is confidently wrong.

1. **Re-read its doc first** and note the claims you are about to invalidate.
2. **Correct the stale sections in the same pass.** Rewrite the claim; don't append a contradiction beside it.
3. **Strip the docblock prose in that domain** — for every file under *Related files*, harvest any fact the doc lacks,
   then delete the comment. Leave tags and the bare pointer.
4. If code and doc disagree and you cannot tell which is right, **the code is right** — it is what runs. Fix the doc,
   and say so in your reply rather than fixing it silently.
