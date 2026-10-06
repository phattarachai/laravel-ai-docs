import { memo, useCallback, useEffect, useRef, useState } from 'react'
import { Link, router } from '@inertiajs/react'

import './ai-docs.css'
import './highlight.css'
import { copyText } from './copy'
import { DocNav, locate } from './DocNav'
import { DocSearch } from './DocSearch'
import { DocToc } from './DocToc'
import { DocZoom } from './DocZoom'
import { useMermaid } from './Mermaid'
import { readPanes, writePanes } from './panes'
import { readScheme, writeScheme } from './scheme'
import { DEFAULT_STRINGS, StringsContext, translate } from './strings'

const NO_GROUPS = []
const NO_PANELS = []

function SunIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <circle cx="8" cy="8" r="3.1" stroke="currentColor" strokeWidth="1.3" />
      <path
        d="M8 1v1.6M8 13.4V15M1 8h1.6M13.4 8H15M3.1 3.1l1.1 1.1M11.8 11.8l1.1 1.1M12.9 3.1l-1.1 1.1M4.2 11.8l-1.1 1.1"
        stroke="currentColor"
        strokeWidth="1.3"
        strokeLinecap="round"
      />
    </svg>
  )
}

function MoonIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <path
        d="M13.4 9.8A5.8 5.8 0 0 1 6.2 2.6a5.8 5.8 0 1 0 7.2 7.2Z"
        stroke="currentColor"
        strokeWidth="1.3"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function OutlineIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <path
        d="M3 4h10M5 8h8M5 12h5"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinecap="round"
      />
    </svg>
  )
}

/** @param {{side: 'left'|'right'}} props which edge the column sits on */
function PanelIcon({ side }) {
  return (
    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <rect x="2" y="3" width="12" height="10" rx="2" stroke="currentColor" strokeWidth="1.3" />
      <path
        d={side === 'left' ? 'M6.2 3v10' : 'M9.8 3v10'}
        stroke="currentColor"
        strokeWidth="1.3"
      />
    </svg>
  )
}

function SearchIcon() {
  return (
    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <circle cx="7" cy="7" r="4.5" stroke="currentColor" strokeWidth="1.4" />
      <path d="M10.5 10.5 14 14" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" />
    </svg>
  )
}

function CopyIcon() {
  return (
    <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <rect
        x="5.4"
        y="5.4"
        width="8.2"
        height="8.2"
        rx="1.8"
        stroke="currentColor"
        strokeWidth="1.3"
      />
      <path
        d="M10.6 5.4V4.2a1.8 1.8 0 0 0-1.8-1.8H4.2a1.8 1.8 0 0 0-1.8 1.8v4.6a1.8 1.8 0 0 0 1.8 1.8h1.2"
        stroke="currentColor"
        strokeWidth="1.3"
      />
    </svg>
  )
}

function PrintIcon() {
  return (
    <svg width="15" height="15" viewBox="0 0 16 16" fill="none" aria-hidden="true">
      <path d="M4.6 6V2.6h6.8V6" stroke="currentColor" strokeWidth="1.3" strokeLinejoin="round" />
      <rect
        x="4.6"
        y="9.4"
        width="6.8"
        height="4"
        stroke="currentColor"
        strokeWidth="1.3"
        strokeLinejoin="round"
      />
      <path
        d="M4.6 6H3.4A1.4 1.4 0 0 0 2 7.4v3A1.4 1.4 0 0 0 3.4 11.8h1.2M11.4 6h1.2A1.4 1.4 0 0 1 14 7.4v3a1.4 1.4 0 0 1-1.4 1.4h-1.2"
        stroke="currentColor"
        strokeWidth="1.3"
        strokeLinecap="round"
      />
    </svg>
  )
}

/**
 * see docs/internals.md — "DocProse is memoized"
 *
 * @param {{html: string, innerRef: {current: HTMLElement|null}}} props
 */
const DocProse = memo(function DocProse({ html, innerRef }) {
  return <article ref={innerRef} className="doc-prose" dangerouslySetInnerHTML={{ __html: html }} />
})

/**
 * The markup full screen shows. An inlined SVG gets its own id prefix there: the
 * copy in the page stays mounted underneath, and two `#marker`s on one page resolve
 * to whichever comes first. see docs/internals.md
 *
 * @param {Element|null|undefined} element
 */
function zoomable(element) {
  const ns = element?.dataset?.docNs

  if (!ns) {
    return element?.outerHTML ?? null
  }

  // `ds-ab12cd-1` is both the root's id and the stem of every inner one.
  const stem = ns.replace(/-$/, '')

  return element.outerHTML.split(stem).join(`${stem}z`)
}

/**
 * see README.md
 *
 * @param {string} base panel root URL
 * @param {{name: string, accent: string, url: string, logo: string|null, tables: 'wrap'|'scroll'}} brand
 * @param {Array<{key: string, label: string, items: Array<{slug: string, title: string, nav: string}>, groups: Array<object>}>} groups nested; `groups` recurses
 * @param {Array<{key: string, label: string, url: string, current: boolean}>} panels sibling trees; empty when there is only one
 * @param {Record<string, string>|undefined} strings copy overrides, from `trans('ai-docs::ui')`
 * @param {{slug: string, title: string, path: string, html: string, toc: Array<{id: string, text: string, level: number}>}|null} page
 */
export default function AiDocs({ base = '/docs', brand, groups, page = null, panels, strings }) {
  const tree = groups ?? NO_GROUPS
  const sections = panels ?? NO_PANELS
  const name = brand?.name ?? 'Docs'
  const mode = brand?.tables === 'scroll' ? 'scroll' : 'wrap'
  // One slot, so the two drawers can never be open at once on a phone.
  const [drawer, setDrawer] = useState(null)
  // Independent of `drawer`: this is the wide-screen column, not the phone overlay.
  const [panes, setPanes] = useState(readPanes)
  const [scheme, setScheme] = useState(readScheme)
  const [finding, setFinding] = useState(false)
  const [zoom, setZoom] = useState(null)
  const [toast, setToast] = useState(null)
  const proseRef = useRef(null)
  const t = useCallback((key, values) => translate(strings, key, values), [strings])
  const timer = useRef(0)

  const trail = (page ? locate(tree, page.slug) : null) ?? []
  const crumb = trail
    .map((group) => group.label)
    .filter(Boolean)
    .join(' · ')

  useMermaid(proseRef, page?.slug, scheme)

  const flash = useCallback((message) => {
    setToast(message)
    window.clearTimeout(timer.current)
    timer.current = window.setTimeout(() => setToast(null), 1600)
  }, [])

  useEffect(() => () => window.clearTimeout(timer.current), [])

  useEffect(() => {
    const keys = (event) => {
      if (event.key === 'k' && (event.metaKey || event.ctrlKey)) {
        event.preventDefault()
        setDrawer(null)
        setFinding((open) => !open)

        return
      }

      if (event.key === 'Escape') {
        setFinding(false)
        setZoom(null)
      }
    }

    window.addEventListener('keydown', keys)

    return () => window.removeEventListener('keydown', keys)
  }, [])

  const flip = () => {
    const next = scheme === 'dark' ? 'light' : 'dark'

    setScheme(next)
    writeScheme(next)
  }

  const toggle = (which) => setDrawer((current) => (current === which ? null : which))

  const collapse = (which) => {
    const next = { ...panes, [which]: !panes[which] }

    setPanes(next)
    writePanes(next)
  }

  const copyPath = async () => {
    if (await copyText(page.path)) {
      flash(t('page.pathCopied'))
    }
  }

  // Doc-to-doc links are plain anchors the markdown renderer wrote; keep the ones that
  // stay inside the panel on the Inertia side.
  const intercept = (event) => {
    const opener = event.target.closest?.('.doc-zoom')

    if (opener) {
      setZoom(zoomable(opener.nextElementSibling))

      return
    }

    if (event.target.tagName === 'IMG') {
      setZoom(event.target.outerHTML)

      return
    }

    const anchor = event.target.closest?.('a[href]')

    if (!anchor || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey) {
      return
    }

    const url = new URL(anchor.getAttribute('href'), window.location.href)

    if (anchor.classList.contains('doc-anchor')) {
      copyText(url.href).then((copied) => copied && flash(t('page.linkCopied')))

      return
    }

    if (anchor.target === '_blank' || url.origin !== window.location.origin) {
      return
    }

    // A cross-panel link is still our own page, so keep it on the Inertia side too.
    const roots = (sections.length > 0 ? sections.map((section) => section.url) : [base]).map(
      (each) => String(each).replace(/\/+$/, ''),
    )

    if (!roots.some((root) => url.pathname === root || url.pathname.startsWith(`${root}/`))) {
      return
    }

    event.preventDefault()
    router.visit(url.pathname + url.search + url.hash)
  }

  const hasOutline = (page?.toc?.length ?? 0) > 0

  return (
    <StringsContext.Provider value={strings ?? DEFAULT_STRINGS}>
      <div
        className={`doc-root mode-${mode} scheme-${scheme}${drawer ? ` drawer-${drawer}` : ''}${
          panes.nav ? ' hide-nav' : ''
        }${panes.toc ? ' hide-toc' : ''}`}
        style={{ '--doc-accent': brand?.accent }}
      >
        <header className="doc-top">
          <div className="doc-topinner">
            <button
              type="button"
              className="doc-iconbtn doc-navtoggle"
              onClick={() => toggle('nav')}
              aria-expanded={drawer === 'nav'}
              aria-label={t('nav.pages')}
            >
              <span className="doc-burger" />
            </button>

            <button
              type="button"
              className="doc-iconbtn doc-panetoggle for-nav"
              onClick={() => collapse('nav')}
              aria-pressed={panes.nav}
              aria-label={panes.nav ? t('nav.showPages') : t('nav.hidePages')}
              title={panes.nav ? t('nav.showPages') : t('nav.hidePages')}
            >
              <PanelIcon side="left" />
            </button>

            <a className="doc-home" href={brand?.url || '/'}>
              {brand?.logo ? (
                <img className="doc-mark doc-mark-img" src={brand.logo} alt="" />
              ) : (
                <span className="doc-mark">{name.slice(0, 1).toUpperCase()}</span>
              )}
              <span className="doc-brand">{name}</span>
            </a>

            {sections.length > 0 && (
              <nav className="doc-panels" aria-label={t('nav.panels')}>
                {sections.map((section) => (
                  <Link
                    key={section.key}
                    href={section.url}
                    className={`doc-panel${section.current ? ' on' : ''}`}
                    aria-current={section.current ? 'page' : undefined}
                  >
                    {section.label}
                  </Link>
                ))}
              </nav>
            )}

            {page && (
              <span className="doc-crumb">
                {crumb ? `${crumb} · ` : ''}
                <b>{page.title}</b>
              </span>
            )}

            <div className="doc-tools">
              <button type="button" className="doc-searchbtn" onClick={() => setFinding(true)}>
                <SearchIcon />
                <span>{t('search.button')}</span>
                <kbd>⌘K</kbd>
              </button>

              {hasOutline && (
                <>
                  <button
                    type="button"
                    className="doc-iconbtn doc-panetoggle for-toc"
                    onClick={() => collapse('toc')}
                    aria-pressed={panes.toc}
                    aria-label={panes.toc ? t('nav.showOutline') : t('nav.hideOutline')}
                    title={panes.toc ? t('nav.showOutline') : t('nav.hideOutline')}
                  >
                    <PanelIcon side="right" />
                  </button>

                  <button
                    type="button"
                    className="doc-iconbtn doc-toctoggle"
                    onClick={() => toggle('toc')}
                    aria-expanded={drawer === 'toc'}
                    aria-label={t('nav.outline')}
                  >
                    <OutlineIcon />
                  </button>
                </>
              )}
              {page && (
                <button
                  type="button"
                  className="doc-iconbtn"
                  onClick={() => window.print()}
                  aria-label={t('page.print')}
                  title={t('page.print')}
                >
                  <PrintIcon />
                </button>
              )}
              <button
                type="button"
                className="doc-iconbtn"
                onClick={flip}
                aria-label={scheme === 'dark' ? t('scheme.toLight') : t('scheme.toDark')}
              >
                {scheme === 'dark' ? <SunIcon /> : <MoonIcon />}
              </button>
            </div>
          </div>
        </header>

        <div className="doc-body">
          <DocNav
            base={base}
            groups={tree}
            current={page?.slug ?? null}
            openKeys={trail.map((group) => group.key)}
            onNavigate={() => setDrawer(null)}
          />

          <main className="doc-main">
            {page ? (
              <div className="doc-page" onClick={intercept}>
                <button
                  type="button"
                  className="doc-copypath"
                  onClick={copyPath}
                  title={page.path}
                  aria-label={t('page.copyPath', { path: page.path })}
                >
                  <CopyIcon />
                </button>

                <DocProse html={page.html} innerRef={proseRef} />
              </div>
            ) : (
              <p className="doc-empty">{t('page.empty')}</p>
            )}
          </main>

          <DocToc
            key={page?.slug ?? 'empty'}
            toc={page?.toc}
            slug={page?.slug}
            onJump={() => setDrawer(null)}
          />
        </div>

        {finding && <DocSearch base={base} onClose={() => setFinding(false)} />}

        {zoom && <DocZoom html={zoom} onClose={() => setZoom(null)} />}

        {toast && <div className="doc-toast">{toast}</div>}

        <button type="button" className="doc-scrim" tabIndex={-1} onClick={() => setDrawer(null)} />
      </div>
    </StringsContext.Provider>
  )
}
