import { createContext, useCallback, useContext } from 'react'

/**
 * Every user-visible string in the docs panel, keyed by a flat dotted key grouped
 * by area (`nav.*`, `scheme.*`, `search.*`, `page.*`, `zoom.*`).
 *
 * The defaults are English so the module renders standalone with zero props. A
 * host overrides any subset by passing `strings` to `<AiDocs>`; the Laravel side
 * ships the same flat keys in `lang/{locale}/ui.php`.
 *
 * The lang files also carry `callout.*` and `anchor.label`, which are rendered
 * server-side into the markdown cache and never reach this module.
 *
 * Placeholders use Laravel's `:name` convention (see `interpolate`).
 */
export const DEFAULT_STRINGS = {
  'nav.pages': 'Pages',
  'nav.outline': 'On this page',

  'scheme.toLight': 'Switch to light',
  'scheme.toDark': 'Switch to dark',

  'search.button': 'Search docs…',
  'search.label': 'Search docs',
  'search.loading': 'Loading…',
  'search.hint': 'Search every page — titles, headings and body text.',
  'search.noMatch': 'No match for “:query”.',
  'search.close': 'Esc',

  'page.empty': 'No documentation pages yet.',
  'page.copyPath': 'Copy :path',
  'page.pathCopied': 'Path copied',
  'page.linkCopied': 'Link copied',

  'zoom.open': 'Full screen',
  'zoom.close': 'Esc',
}

export const StringsContext = createContext(DEFAULT_STRINGS)

/**
 * Substitute Laravel-style `:name` placeholders. Longer keys are replaced first
 * so `:path` never eats the head of `:pathName`.
 */
export function interpolate(template, replacements) {
  if (!replacements) {
    return template
  }

  return Object.keys(replacements)
    .sort((a, b) => b.length - a.length)
    .reduce((out, key) => out.replaceAll(`:${key}`, String(replacements[key] ?? '')), template)
}

/**
 * Resolve one key against `strings`, falling back to the English default and then
 * to the key itself — so a missing key never renders `undefined`.
 */
export function translate(strings, key, replacements) {
  return interpolate(strings?.[key] ?? DEFAULT_STRINGS[key] ?? key, replacements)
}

/**
 * @returns {(key: string, replacements?: Record<string, unknown>) => string} the
 *   `t` translator bound to the nearest `StringsContext`.
 */
export function useStrings() {
  const strings = useContext(StringsContext)

  return useCallback((key, replacements) => translate(strings, key, replacements), [strings])
}
