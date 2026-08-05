import { useEffect, useState } from 'react'

import { useStrings } from './strings'

/** see README.md */
export function DocToc({ toc, slug, onJump }) {
  const t = useStrings()
  const [active, setActive] = useState(null)

  useEffect(() => {
    const items = toc ?? []
    const ids = items.map((item) => item.id)
    const nodes = ids.map((id) => document.getElementById(id)).filter(Boolean)

    if (nodes.length === 0) {
      return undefined
    }

    const visible = new Set()

    const pick = () => {
      const first = ids.find((id) => visible.has(id))

      if (first) {
        setActive(first)

        return
      }

      // Nothing in the band — the last heading already scrolled past. Guarded on
      // scrollY because a mid-flight reflow (a mermaid diagram drawing, a theme
      // flip redrawing them all) briefly puts every heading above the fold.
      if (window.scrollY < 4) {
        setActive(ids[0])

        return
      }

      let above = null

      for (const node of nodes) {
        if (node.getBoundingClientRect().top < 120) {
          above = node.id
        }
      }

      setActive(above ?? ids[0])
    }

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            visible.add(entry.target.id)
          } else {
            visible.delete(entry.target.id)
          }
        }

        pick()
      },
      { rootMargin: '-72px 0px -62% 0px', threshold: 0 },
    )

    nodes.forEach((node) => observer.observe(node))

    // The observer alone can settle on a stale answer after a reflow that moved
    // content without crossing a threshold; scrolling re-decides from geometry.
    let frame = 0
    const onScroll = () => {
      cancelAnimationFrame(frame)
      frame = requestAnimationFrame(pick)
    }

    window.addEventListener('scroll', onScroll, { passive: true })

    return () => {
      observer.disconnect()
      cancelAnimationFrame(frame)
      window.removeEventListener('scroll', onScroll)
    }
  }, [toc, slug])

  if (!toc || toc.length === 0) {
    return <aside className="doc-toc" />
  }

  const jump = (event, id) => {
    const node = document.getElementById(id)

    if (!node) {
      return
    }

    event.preventDefault()
    onJump?.()
    node.scrollIntoView({ behavior: 'smooth', block: 'start' })
    setActive(id)
    window.history.replaceState(null, '', `#${id}`)
  }

  return (
    <aside className="doc-toc">
      <div className="hd">{t('nav.outline')}</div>
      {toc.map((item) => (
        <a
          key={item.id}
          href={`#${item.id}`}
          onClick={(event) => jump(event, item.id)}
          className={`lv${item.level}${active === item.id ? ' on' : ''}`}
        >
          {item.text}
        </a>
      ))}
    </aside>
  )
}
