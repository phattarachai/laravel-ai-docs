---
name: ai-docs-notes
description: 'Publish an explanation page to the running app with `php artisan ai-docs:push`, and pull it back with `ai-docs:pull`, through laravel-ai-docs note panels: no commit, no deploy. Use when writing how a flow or calculation works for the people who use the system, when asked to "push a note", "explain this on staging", "อธิบาย flow ให้ user ดู", or when given a /docs/notes URL to update.'
---

# Notes in laravel-ai-docs

A note panel (`'notes' => true` in `config/ai-docs.php`) holds pages pushed to the running app. They render like
repo docs (sidebar, search, mermaid, images) but are not in git.

## Note or doc?

- **Push a note** when the page explains the system to the people who use it, should be readable on the app now, and
  will likely be rewritten after their feedback.
- **Commit a doc** under the docs tree (`.ai/documents`) when it is for whoever changes the code, or records a
  decision or contract that should change in the same review as the code.

When unsure, push a note. Promoting it later is a move into the docs tree and a commit.

## Write

- Work in the working copy, `.ai/<panel>/` (usually `.ai/notes/`). It is gitignored; never commit it.
- The path is the category: `.ai/notes/flows/cart/price-recalc.md` shows as **Flows › Cart**. Name a folder with an
  `index.md` carrying `group: …` front matter.
- Keep images beside the page and link them relatively: `![ตะกร้า](price-recalc.png)`. Mermaid fences work.
- Don't write `updated:` or `author:` yourself; the push stamps both.

## Commands

```bash
php artisan ai-docs:pull                                   # list the notes on the app
php artisan ai-docs:pull flows/cart/price-recalc           # or the page URL; writes .ai/notes/...
php artisan ai-docs:push .ai/notes/flows/cart/price-recalc.md
php artisan ai-docs:push .ai/notes/flows/order --prune     # a folder; --prune removes what it no longer has
php artisan ai-docs:push flows/cart/price-recalc --delete
```

- **Pull before you edit** a note someone may have pushed since: the last push wins, and there is no conflict check.
- A file outside the working copy needs `--path=flows/cart/price-recalc`.
- Push prints each page's URL; give that URL to the user.
- A 401 means no token: the user runs `php artisan ai-docs:token --write` and puts the printed hash in the server's
  `AI_DOCS_TOKEN_HASHES`. Never ask for the plaintext token in chat.
