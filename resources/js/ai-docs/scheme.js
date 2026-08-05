/** see README.md */
const KEY = 'ai-docs:scheme'

function safely(action, fallback = null) {
  try {
    return action()
  } catch {
    return fallback
  }
}

export function readScheme() {
  const stored = safely(() => window.localStorage.getItem(KEY))

  if (stored === 'light' || stored === 'dark') {
    return stored
  }

  if (document.documentElement.classList.contains('dark')) {
    return 'dark'
  }

  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

export function writeScheme(scheme) {
  safely(() => window.localStorage.setItem(KEY, scheme))
}
