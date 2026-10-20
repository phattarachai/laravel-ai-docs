import { useEffect } from 'react'

/** see README.md — "Embed" */
export const EMBED_MESSAGE = 'ai-docs:page'

const EMBED_PARAMS = ['embed', 'scheme']

/** An embedded page follows the host's `?scheme=`, not the scheme saved for the full panel. */
export function embedScheme() {
  return new URLSearchParams(window.location.search).get('scheme') === 'dark' ? 'dark' : 'light'
}

/** The address of the same page outside the iframe: the embed params dropped, the heading kept. */
export function fullUrl(href) {
  const url = new URL(href)

  EMBED_PARAMS.forEach((param) => url.searchParams.delete(param))

  return url.href
}

/**
 * Tells the host which page the iframe shows, on load and after every link followed
 * inside it, so the host can title its panel and open the same page full size.
 *
 * @param {boolean} embed
 * @param {{slug: string, title: string}|null} page
 */
export function useEmbedReport(embed, page) {
  useEffect(() => {
    if (!embed || window.parent === window) {
      return
    }

    window.parent.postMessage(
      { type: EMBED_MESSAGE, title: page?.title ?? null, url: fullUrl(window.location.href) },
      window.location.origin,
    )
  }, [embed, page?.slug, page?.title])
}

function scrollToHash() {
  const id = decodeURIComponent(window.location.hash.slice(1))
  const heading = id ? document.getElementById(id) : null

  if (!heading) {
    return
  }

  const margin = parseFloat(window.getComputedStyle(heading).scrollMarginTop) || 0

  window.scrollTo({ top: heading.getBoundingClientRect().top + window.scrollY - margin })
}

let following = false

/**
 * Scrolls to the `#heading` in the address after every Inertia visit, the first load
 * included. Inertia's own anchor scroll looks the hash up undecoded, so a non-ASCII
 * heading id (Thai) is never found and it scrolls to the top in a timer. It fires
 * `inertia:navigate` right after queueing that timer, so a timer queued from the event
 * runs after it. `window.scrollTo`, because `scrollIntoView` inside an iframe scrolls
 * the host page too.
 */
export function followHash() {
  if (following || typeof document === 'undefined') {
    return
  }

  following = true
  document.addEventListener('inertia:navigate', () => window.setTimeout(scrollToHash))
}
