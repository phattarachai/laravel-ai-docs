# Authoring — shaping the folder this panel renders

The panel reads whatever directory you point `root` at, so the quality of the site is entirely the quality of the
folder. This is the shape that stays useful once there are thirty files in it — and once an AI agent, not just a
person, is the one loading them.

None of it is enforced by the package. It is the convention the author writes docs with, and the panel is built to
reward it.

## Flat first

**A folder needs at least two docs.** One doc alone in a directory is scatter: the reader opens a group in the sidebar
to find a single entry, and the group buys nothing. Put that doc at the root of the tree instead, and create the folder
the day a second doc joins it. When a folder drops back to one doc — a merge, a deletion — flatten it in the same
change.

**Cross-cutting docs live flat.** Auth, testing, infrastructure, the design system: these apply across every domain
rather than belonging to one, and burying them under a folder hides them from the reader who needs them most. They sit
at the root, where the panel lists them ungrouped above every accordion.

Prefer few folders. **Two or three folders plus a handful of root files** is the target shape — not a folder per topic.
A sidebar of twelve groups holding two files each is a filing cabinet, not a map; the reader has to open every drawer
before they know what exists.

## `index.md` is the map

`index.md` at the root of the docs folder is the panel's landing page. It is also, in practice, the file a reader or an
agent loads first to find out what else is here — so it has to be a complete list, not a welcome message.

One line per doc, grouped by folder, each line saying what that doc owns and when you would open it:

```markdown
## billing

- [billing/subscriptions.md](billing/subscriptions.md) — plan changes, proration, and what a downgrade
  does to an active period. Read before touching the plan picker.
- [billing/invoicing.md](billing/invoicing.md) — invoice generation, the PDF pipeline, and tax lines.
```

**Update it in the same change that adds, renames, moves, or deletes a doc.** An unindexed doc may as well not exist:
a fresh session reads the index and stops. This is the one rule with no slack in it, because nothing fails when it is
broken — the doc is simply invisible, and stays invisible for months.

A folder with its own `index.md` gets it sorted first inside that group, so a large domain can carry a local map as
well. The root index still lists every doc; the folder index is a convenience, never a substitute.

## One feature, one file — 500 lines hard

One feature, one doc. **A doc may not exceed 500 lines.**

Treat approaching that ceiling as a signal that **a second subject is hiding inside the first**, not as a cue to
compress. Trimming to fit deletes the rationale the doc existed to carry and leaves the reader back at the code, which
is exactly the trip the doc was written to save.

So extract:

- **Name the new doc for what it owns** — `webhooks-retry.md`, never `webhooks-part-2.md`. The name is the test: if you
  cannot name the second subject, you have not found a real seam. Look again before cutting.
- **Cross-link both ways.** The parent names the child where the topic hands off; the child names its parent in the
  first line. Relative links between docs are rewritten to panel URLs and navigate through Inertia, anchors included,
  so a link costs the reader nothing.
- **Index the child** in the same change, with its own one-line description.

Under the ceiling, leave a cohesive doc alone. Splitting a 200-line feature into four 50-line files produces fragments
that only make sense read together, and the reader now needs four sidebar clicks to learn one thing.

## Describe the system as it is now

**A doc describes the system as it is today.** No `## History`, no `## Changelog`, no `## Previously`, no
`## Migration notes`, no running log of what each release changed.

That content is real and worth keeping — it just belongs in whatever tracks your work: the issue, the PR, the task
file, the commit. Where the history genuinely matters to a reader, the doc carries a pointer to it and no retelling.

**Superseded behaviour is deleted, not archived.** If the doc explains how something works now, the old way is noise
competing for the reader's attention, and the change record is where someone goes to find out why it moved.

The one exception is a fact the *current* system still depends on — a legacy payload still being parsed, a column still
written for backwards compatibility. That is not history; it is present behaviour with an unfortunate origin. Document
it as current, and say what still depends on it.

## No documentation in docblocks

**A docblock may not contain a sentence.** Why a decision was made, what breaks if you undo it, the measurement behind
a constant, the bug that produced a guard — all of it goes in the docs tree, and nowhere else.

Two copies of one fact drift, and the code copy is the one nobody updates. Once they disagree you cannot tell which is
current without reading the implementation — the exact trip the doc existed to save you.

What stays in the code:

- Type and tooling annotations that IDEs and static analysis consume: `@param`, `@return`, `@var`, `@property`,
  `@throws`, `@template`, generics, array shapes. Tags only — a `@param` whose description grows into an explanation is
  prose wearing a tag.
- **A bare pointer**: `see docs/billing/invoicing.md`. This is the intended way for code to hand a reader to its
  documentation, and it survives because it carries no fact that can go stale. This package's own source does exactly
  that — every class carries `@see README.md` and nothing more.

Everything else goes, including a comment that merely restates the doc.

## Ordering and labels

Two front-matter keys steer the sidebar, and both are optional. With no front matter at all, the nav label is the `H1`,
cut at the first ` — `, ` – `, ` (` or `: ` — so `# Invoicing — generation, PDFs and tax lines` lists as **Invoicing**.

```yaml
---
title: Invoicing — generation, PDFs and tax lines
nav: Invoicing
order: 10
---
```

`title` sets the page title and browser tab, falling back to the H1 and then the filename. `nav` sets the sidebar
label, overriding the shortened title. `order` sets the position within the group.

**Reach for a key only when the default lands badly** — which is rarer than it looks, because the panel already handles
the usual cause. Three docs titled `Billing — …` would all shorten to *Billing*; when that happens the panel takes the
half of the title *after* the cut instead, so they list as *subscriptions*, *invoicing*, *dunning*. Write `nav` when you
want a label the title does not contain, not merely to break a tie.

The sort runs in two stages. Groups come first: the root group is always at the top, then every folder in path order.
Inside a group, pages sort by `order` (a page without one sorts as **500**, so a single `order: 10` lifts one doc to
the top without renumbering its siblings), then `index.md` before everything else, then nav label alphabetically.

Group labels are derived from the directory name and cannot be overridden — the panel uppercases the first letter and
turns `-` and `_` into spaces, so `ui-kit` renders as *Ui kit*. **The folder name is the only lever you have on a group
label**, which is a reason to prefer single-word folder names.

## Nesting

A folder inside a folder nests inside its group rather than listing beside it, at any depth. `billing/dunning/` opens
within **Billing**, and a page there breadcrumbs as `Billing · Dunning`.

That is a rendering guarantee, not an invitation. Everything under "Flat first" still holds: the reader has to open two
drawers to reach a nested doc rather than one. Nest when a domain genuinely has a sub-domain with several docs of its
own — a pricing engine inside a cart, one strategy per file — and keep the parent folder's own docs directly in it, so
the group is never an empty shell holding only more groups.

## A good tree looks like this

```
.ai/documents/
├── index.md                 # the map — every doc below, one line each
├── auth.md                  # cross-cutting: sessions, tokens, the gate
├── testing.md               # cross-cutting: the suite, fixtures, what CI runs
├── infrastructure.md        # cross-cutting: queues, scheduler, deploy
├── search.md                # one feature, no folder — nothing joins it yet
├── billing/
│   ├── index.md             # local map for a large domain
│   ├── subscriptions.md
│   ├── invoicing.md
│   ├── dunning.md
│   └── invoice-pdf.png      # image beside the doc that references it
└── catalog/
    ├── products.md
    ├── pricing.md
    └── inventory.md
```

Eleven docs, two folders, four cross-cutting files at the root. `search.md` sits flat because it is alone; the day a
second search doc is written, both move into `search/` and the index gains a heading. That is the whole system.
