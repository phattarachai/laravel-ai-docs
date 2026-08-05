import { useState } from 'react'
import { Link } from '@inertiajs/react'

function href(base, slug) {
  return `${String(base ?? '').replace(/\/+$/, '')}/${slug}`
}

/** see README.md */
export function DocNav({ base, groups, current, currentGroup, onNavigate }) {
  const [open, setOpen] = useState(() => new Set())
  const [tracked, setTracked] = useState(null)

  if (currentGroup && tracked !== currentGroup) {
    setTracked(currentGroup)
    setOpen((previous) => new Set(previous).add(currentGroup))
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
      {groups.map((group) => {
        const expanded = group.label === '' || open.has(group.key)

        return (
          <div key={group.key}>
            {group.label !== '' && (
              <button
                type="button"
                className={`doc-acc${group.key === currentGroup ? ' on' : ''}`}
                onClick={() => toggle(group.key)}
                aria-expanded={expanded}
              >
                <span>{group.label}</span>
                <span className="chev" aria-hidden="true">
                  {expanded ? '▾' : '▸'}
                </span>
              </button>
            )}

            {expanded &&
              group.items.map((item) => (
                <Link
                  key={item.slug}
                  href={href(base, item.slug)}
                  preserveScroll={false}
                  onClick={onNavigate}
                  className={`doc-link${item.slug === current ? ' on' : ''}`}
                >
                  {item.nav || item.title}
                </Link>
              ))}
          </div>
        )
      })}
    </nav>
  )
}
