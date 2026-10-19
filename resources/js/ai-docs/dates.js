const DATE_ONLY = /^\d{4}-\d{2}-\d{2}$/

/**
 * see README.md — "Writing docs"
 *
 * @param {string} value `YYYY-MM-DD` or an ISO 8601 date-time, from the page's `updated` front matter
 * @param {string|undefined} locale
 */
export function formatUpdated(value, locale) {
  const dateOnly = DATE_ONLY.test(value)
  const date = new Date(dateOnly ? `${value}T00:00:00` : value)

  if (Number.isNaN(date.getTime())) {
    return value
  }

  const options = dateOnly
    ? { day: 'numeric', month: 'short', year: 'numeric' }
    : { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }

  try {
    return new Intl.DateTimeFormat(locale, options).format(date)
  } catch {
    return new Intl.DateTimeFormat(undefined, options).format(date)
  }
}
