/** see README.md — "Collapsing a column" */
const KEY = 'ai-docs:panes'

const BOTH_OPEN = { nav: false, toc: false }

function safely(action, fallback = null) {
  try {
    return action()
  } catch {
    return fallback
  }
}

/**
 * Which columns the reader has collapsed. Both start open: a docs panel that
 * hides its own tree on first visit reads as broken, not as tidy.
 *
 * @returns {{nav: boolean, toc: boolean}} `true` means collapsed
 */
export function readPanes() {
  const stored = safely(() => JSON.parse(window.localStorage.getItem(KEY)))

  if (!stored || typeof stored !== 'object') {
    return BOTH_OPEN
  }

  return { nav: stored.nav === true, toc: stored.toc === true }
}

export function writePanes(panes) {
  safely(() => window.localStorage.setItem(KEY, JSON.stringify(panes)))
}
