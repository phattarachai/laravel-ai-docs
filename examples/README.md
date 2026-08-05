# Examples

Optional extras. Nothing here is loaded by the package, published by a vendor tag, or required to run the panel — copy
what you want into your own project and edit it.

## `document-skill/`

A [Claude Code](https://claude.com/claude-code) skill that teaches an agent to write and maintain the docs tree this
panel renders: the flat-first structure, the `index.md` invariant, the 500-line ceiling, and the front matter, callouts
and mermaid classes this renderer understands. It is the conventions in [`../docs/authoring.md`](../docs/authoring.md),
written as instructions instead of prose.

Drop it in the host project at:

```
.claude/skills/document/SKILL.md
```

Then say "document this" after a feature lands. Adjust the docs path in it if your `ai-docs.root` is not
`.ai/documents`.
