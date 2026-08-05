import { useEffect, useMemo, useRef, useState } from 'react'
import { router } from '@inertiajs/react'

import { useStrings } from './strings'

const PER_DOC = 3
const LIMIT = 40

let index = null

function load(base) {
  index =
    index ??
    fetch(`${String(base).replace(/\/+$/, '')}/_search.json`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
      .then((response) => (response.ok ? response.json() : []))
      .catch(() => [])

  return index
}

function words(query) {
  return query.trim().toLowerCase().split(/\s+/).filter(Boolean)
}

function snippet(text, needles) {
  const lower = text.toLowerCase()
  const at = needles.map((needle) => lower.indexOf(needle)).filter((position) => position >= 0)

  if (at.length === 0) {
    return text.slice(0, 150)
  }

  const start = Math.max(0, Math.min(...at) - 60)

  return (start > 0 ? '…' : '') + text.slice(start, start + 180)
}

function score(doc, section, needles) {
  const title = doc.title.toLowerCase()
  const heading = section.heading.toLowerCase()
  const text = section.text.toLowerCase()

  if (!needles.every((needle) => `${title} ${heading} ${text}`.includes(needle))) {
    return 0
  }

  let total = section.level <= 1 ? 12 : 0

  for (const needle of needles) {
    total += title.includes(needle) ? 40 : 0
    total += heading.includes(needle) ? 25 : 0
    total += text.includes(needle) ? 8 : 0
  }

  return total
}

function search(docs, query) {
  const needles = words(query)

  if (needles.length === 0) {
    return []
  }

  const found = []

  for (const doc of docs) {
    const own = []

    for (const section of doc.sections) {
      const total = score(doc, section, needles)

      if (total > 0) {
        own.push({
          key: `${doc.slug}#${section.id}`,
          slug: doc.slug,
          docTitle: doc.title,
          group: doc.group,
          heading: section.heading,
          hash: section.level <= 1 ? '' : `#${section.id}`,
          snippet: snippet(section.text, needles),
          total,
        })
      }
    }

    own.sort((a, b) => b.total - a.total)
    found.push(...own.slice(0, PER_DOC))
  }

  found.sort((a, b) => b.total - a.total)

  return found.slice(0, LIMIT)
}

function escape(term) {
  return term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

function Marked({ text, needles }) {
  if (needles.length === 0 || text === '') {
    return text
  }

  const pattern = new RegExp(`(${needles.map(escape).join('|')})`, 'ig')

  return text
    .split(pattern)
    .map((part, position) =>
      needles.includes(part.toLowerCase()) ? <mark key={position}>{part}</mark> : part,
    )
}

/** see README.md */
export function DocSearch({ base, onClose }) {
  const t = useStrings()
  const [docs, setDocs] = useState(null)
  const [query, setQuery] = useState('')
  const [cursor, setCursor] = useState(0)
  const [cursorFor, setCursorFor] = useState('')
  const listRef = useRef(null)

  if (cursorFor !== query) {
    setCursorFor(query)
    setCursor(0)
  }

  useEffect(() => {
    let live = true

    load(base).then((loaded) => live && setDocs(loaded))

    return () => {
      live = false
    }
  }, [base])

  const needles = useMemo(() => words(query), [query])
  const results = useMemo(() => search(docs ?? [], query), [docs, query])

  useEffect(() => {
    listRef.current?.querySelector('.on')?.scrollIntoView({ block: 'nearest' })
  }, [cursor, results])

  const go = (result) => {
    if (!result) {
      return
    }

    onClose()
    router.visit(`${String(base).replace(/\/+$/, '')}/${result.slug}${result.hash}`)
  }

  const keys = (event) => {
    const step = { ArrowDown: 1, ArrowUp: -1 }[event.key]

    if (step) {
      event.preventDefault()
      setCursor((current) => (current + step + results.length) % Math.max(results.length, 1))

      return
    }

    if (event.key === 'Enter') {
      event.preventDefault()
      go(results[cursor])
    }
  }

  return (
    <div className="doc-palette" role="dialog" aria-modal="true" aria-label={t('search.label')}>
      <button type="button" className="doc-palette-scrim" tabIndex={-1} onClick={onClose} />

      <div className="doc-palette-box">
        <div className="doc-palette-field">
          <input
            type="text"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            onKeyDown={keys}
            placeholder={t('search.button')}
            spellCheck="false"
            autoComplete="off"
            aria-label={t('search.label')}
            autoFocus
          />
          <button type="button" className="doc-palette-esc" onClick={onClose}>
            {t('search.close')}
          </button>
        </div>

        <div className="doc-palette-list" ref={listRef}>
          {docs === null && <p className="doc-palette-note">{t('search.loading')}</p>}

          {docs !== null && query.trim() === '' && (
            <p className="doc-palette-note">{t('search.hint')}</p>
          )}

          {docs !== null && query.trim() !== '' && results.length === 0 && (
            <p className="doc-palette-note">{t('search.noMatch', { query: query.trim() })}</p>
          )}

          {results.map((result, position) => (
            <button
              type="button"
              key={result.key}
              className={`doc-hit${position === cursor ? ' on' : ''}`}
              onMouseEnter={() => setCursor(position)}
              onClick={() => go(result)}
            >
              <span className="doc-hit-top">
                <b>
                  <Marked text={result.heading || result.docTitle} needles={needles} />
                </b>
                <span className="doc-hit-doc">
                  {result.group === '' ? result.docTitle : `${result.group} · ${result.docTitle}`}
                </span>
              </span>
              <span className="doc-hit-text">
                <Marked text={result.snippet} needles={needles} />
              </span>
            </button>
          ))}
        </div>
      </div>
    </div>
  )
}
