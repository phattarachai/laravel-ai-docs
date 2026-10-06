import { useEffect } from 'react'

import { themeFor } from './mermaid-theme'

let loading = null
let sequence = 0

function load() {
  loading = loading ?? import('mermaid').then(({ default: mermaid }) => mermaid)

  return loading
}

function fallback(source) {
  const pre = document.createElement('pre')

  pre.className = 'doc-mermaid-fallback'
  pre.textContent = source

  return pre
}

async function attempt(mermaid, source) {
  const id = `doc-mermaid-${(sequence += 1)}`

  try {
    return await mermaid.render(id, source)
  } catch (error) {
    document.getElementById(`d${id}`)?.remove()
    console.warn(`[ai-docs] mermaid could not render ${id}.`, error)

    throw error
  }
}

/**
 * see docs/internals.md — "A failed render is retried once when the source parses"
 */
async function draw(mermaid, source) {
  try {
    return await attempt(mermaid, source)
  } catch (error) {
    if (!(await mermaid.parse(source, { suppressErrors: true }))) {
      throw error
    }

    return attempt(mermaid, source)
  }
}

/**
 * see README.md
 *
 * @param {{current: HTMLElement|null}} containerRef the rendered-HTML container
 * @param {string|undefined} slug current page slug
 * @param {'light'|'dark'} scheme
 */
export function useMermaid(containerRef, slug, scheme) {
  useEffect(() => {
    const container = containerRef.current

    if (!container) {
      return undefined
    }

    const theme = scheme === 'dark' ? 'dark' : 'light'

    // `data-drawn` is ours; a node pre-filled by a build step has none and is left alone.
    const nodes = Array.from(container.querySelectorAll('.doc-mermaid[data-src]')).filter(
      (node) => !node.querySelector('svg') || node.dataset.drawn !== theme,
    )

    if (nodes.length === 0) {
      return undefined
    }

    let cancelled = false

    load()
      .then(async (mermaid) => {
        mermaid.initialize(themeFor(theme))

        for (const node of nodes) {
          if (cancelled) {
            return
          }

          const source = node.getAttribute('data-src') ?? ''

          try {
            const { svg, bindFunctions } = await draw(mermaid, source)

            if (cancelled) {
              return
            }

            node.innerHTML = svg
            node.dataset.drawn = theme
            bindFunctions?.(node)
          } catch {
            node.replaceChildren(fallback(source))
            node.dataset.drawn = theme
          }
        }
      })
      .catch((error) => {
        console.warn(
          '[ai-docs] mermaid could not be loaded; showing diagram sources instead.',
          error,
        )
        nodes.forEach((node) => node.replaceChildren(fallback(node.getAttribute('data-src') ?? '')))
      })

    return () => {
      cancelled = true
    }
  }, [containerRef, slug, scheme])
}
