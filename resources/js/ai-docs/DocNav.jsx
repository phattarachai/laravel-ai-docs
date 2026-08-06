import { useState } from 'react'
import { Link } from '@inertiajs/react'

function href(base, slug) {
  return `${String(base ?? '').replace(/\/+$/, '')}/${slug}`
}

/**
 * Walk to the doc and hand back the groups on the way, outermost first — the panel needs
 * the whole trail, not just the leaf, so a nested folder opens its ancestors with it.
 *
 * see docs/internals.md — "Nested groups"
 */
export function locate(groups, slug, trail = []) {
  for (const group of groups) {
    const next = [...trail, group]

    if (group.items?.some((doc) => doc.slug === slug)) {
      return next
    }

    const found = locate(group.groups ?? [], slug, next)

    if (found) {
      return found
    }
  }

  return null
}

function Level({ base, groups, depth, current, open, toggle, onNavigate }) {
  return groups.map((group) => {
    const expanded = group.label === '' || open.has(group.key)

    return (
      <div key={group.key}>
        {group.label !== '' && (
          <button
            type="button"
            className={`doc-acc${depth > 0 ? ' sub' : ''}${group.key === current.group ? ' on' : ''}`}
            style={{ '--doc-depth': depth }}
            onClick={() => toggle(group.key)}
            aria-expanded={expanded}
          >
            <span>{group.label}</span>
            <span className="chev" aria-hidden="true">
              {expanded ? '▾' : '▸'}
            </span>
          </button>
        )}

        {expanded && (
          <>
            {group.items.map((item) => (
              <Link
                key={item.slug}
                href={href(base, item.slug)}
                preserveScroll={false}
                onClick={onNavigate}
                className={`doc-link${item.slug === current.slug ? ' on' : ''}`}
                style={{ '--doc-depth': depth }}
              >
                {item.nav || item.title}
              </Link>
            ))}

            <Level
              base={base}
              groups={group.groups ?? []}
              depth={depth + 1}
              current={current}
              open={open}
              toggle={toggle}
              onNavigate={onNavigate}
            />
          </>
        )}
      </div>
    )
  })
}

/**
 * see README.md
 *
 * @param {Array<string>} openKeys the current page's group trail, outermost first
 */
export function DocNav({ base, groups, current, openKeys, onNavigate }) {
  const [open, setOpen] = useState(() => new Set())
  const [tracked, setTracked] = useState('')
  const trail = (openKeys ?? []).join('\n')

  if (trail !== '' && tracked !== trail) {
    setTracked(trail)
    setOpen((previous) => {
      const next = new Set(previous)

      openKeys.forEach((key) => next.add(key))

      return next
    })
  }

  const toggle = (key) =>
    setOpen((previous) => {
      const next = new Set(previous)

      if (!next.delete(key)) {
        next.add(key)
      }

      return next
    })

  return (
    <nav className="doc-nav">
      <Level
        base={base}
        groups={groups}
        depth={0}
        current={{ slug: current, group: openKeys?.at(-1) ?? null }}
        open={open}
        toggle={toggle}
        onNavigate={onNavigate}
      />
    </nav>
  )
}
