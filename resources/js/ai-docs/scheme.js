/** see README.md — "Themes" */
const KEY = 'ai-docs:scheme'

/** In menu order. `light` and `dark` are the two the panel shipped with, so a stored choice from before the picker still reads. */
export const SCHEMES = ['light', 'soft', 'nord', 'dark']

/** The schemes drawn light-on-dark — what mermaid and anything else with only two palettes needs to know. */
const DARK = ['nord', 'dark']

function safely(action, fallback = null) {
  try {
    return action()
  } catch {
    return fallback
  }
}

export function isScheme(value) {
  return SCHEMES.includes(value)
}

export function isDark(scheme) {
  return DARK.includes(scheme)
}

/**
 * The reader's own pick, then the host's configured default, then the host page's
 * `<html class="dark">`, then the OS.
 *
 * @param {string|null|undefined} preferred `brand.theme`, from `ai-docs.theme`
 */
export function readScheme(preferred = null) {
  const stored = safely(() => window.localStorage.getItem(KEY))

  if (isScheme(stored)) {
    return stored
  }

  if (isScheme(preferred)) {
    return preferred
  }

  if (document.documentElement.classList.contains('dark')) {
    return 'dark'
  }

  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function writeScheme(scheme) {
  safely(() => window.localStorage.setItem(KEY, scheme))
}
