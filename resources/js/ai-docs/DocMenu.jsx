import { useEffect, useId, useRef, useState } from 'react'
import { Link } from '@inertiajs/react'

function ChevronIcon() {
  return (
    <svg
      className="doc-menu-chev"
      width="10"
      height="10"
      viewBox="0 0 10 10"
      fill="none"
      aria-hidden="true"
    >
      <path
        d="M2.5 3.8 5 6.3l2.5-2.5"
        stroke="currentColor"
        strokeWidth="1.4"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

function CheckIcon() {
  return (
    <svg
      className="doc-menu-check"
      width="12"
      height="12"
      viewBox="0 0 12 12"
      fill="none"
      aria-hidden="true"
    >
      <path
        d="m2.5 6.2 2.3 2.3 4.7-5"
        stroke="currentColor"
        strokeWidth="1.5"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  )
}

/**
 * A menu button with one checked item: the panel switcher and the theme picker.
 * An item with an `href` is an Inertia link, so ⌘-click still opens it in a tab;
 * one without calls `onSelect`. Arrows, Home/End, Enter/Space and Escape follow the
 * WAI-ARIA menu button pattern, and a press anywhere outside closes it.
 *
 * @param {string} label the menu's accessible name
 * @param {string|undefined} title the trigger's accessible name, when its content is not words
 * @param {import('react').ReactNode} trigger what the closed button shows, before the chevron
 * @param {Array<{key: string, label: string, current: boolean, href?: string, icon?: import('react').ReactNode}>} items
 * @param {(key: string) => void} [onSelect]
 * @param {string} [className] added to the wrapper, for placement
 */
export function DocMenu({ label, title, trigger, items, onSelect, className = '' }) {
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(0)
  const rootRef = useRef(null)
  const buttonRef = useRef(null)
  const itemRefs = useRef([])
  const id = useId()

  const current = Math.max(
    0,
    items.findIndex((item) => item.current),
  )

  useEffect(() => {
    if (open) {
      itemRefs.current[active]?.focus()
    }
  }, [open, active])

  useEffect(() => {
    if (!open) {
      return undefined
    }

    const away = (event) => {
      if (!rootRef.current?.contains(event.target)) {
        setOpen(false)
      }
    }

    document.addEventListener('pointerdown', away)

    return () => document.removeEventListener('pointerdown', away)
  }, [open])

  const show = (index) => {
    setActive(index)
    setOpen(true)
  }

  const close = (refocus) => {
    setOpen(false)

    if (refocus) {
      buttonRef.current?.focus()
    }
  }

  const onButtonKey = (event) => {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
      event.preventDefault()
      show(current)
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      show(items.length - 1)
    }
  }

  const onMenuKey = (event) => {
    const last = items.length - 1
    const moves = {
      ArrowDown: active === last ? 0 : active + 1,
      ArrowUp: active === 0 ? last : active - 1,
      Home: 0,
      End: last,
    }

    if (event.key in moves) {
      event.preventDefault()
      setActive(moves[event.key])

      return
    }

    // Tab too: the list unmounts before the browser moves focus, which would drop it on <body>.
    if (event.key === 'Escape' || event.key === 'Tab') {
      event.preventDefault()
      event.stopPropagation()
      close(true)

      return
    }

    // A link activates on Enter by itself, a button on both; Space on a link does not.
    if (event.key === ' ') {
      event.preventDefault()
      itemRefs.current[active]?.click()
    }
  }

  const choose = (item) => {
    close(!item.href)

    if (!item.href) {
      onSelect?.(item.key)
    }
  }

  return (
    <div ref={rootRef} className={`doc-menu ${className}`}>
      <button
        ref={buttonRef}
        type="button"
        className="doc-menu-btn"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? id : undefined}
        aria-label={title}
        title={title}
        onClick={() => (open ? close(false) : show(current))}
        onKeyDown={onButtonKey}
      >
        {trigger}
        <ChevronIcon />
      </button>

      {open && (
        <div id={id} className="doc-menu-list" role="menu" aria-label={label} onKeyDown={onMenuKey}>
          {items.map((item, index) => {
            const props = {
              ref: (node) => {
                itemRefs.current[index] = node
              },
              role: 'menuitemradio',
              'aria-checked': item.current,
              tabIndex: index === active ? 0 : -1,
              className: `doc-menu-item${item.current ? ' on' : ''}`,
              onClick: () => choose(item),
              onMouseEnter: () => setActive(index),
            }
            const body = (
              <>
                {item.icon}
                <span className="doc-menu-label">{item.label}</span>
                {item.current && <CheckIcon />}
              </>
            )

            return item.href ? (
              <Link key={item.key} {...props} href={item.href}>
                {body}
              </Link>
            ) : (
              <button key={item.key} {...props} type="button">
                {body}
              </button>
            )
          })}
        </div>
      )}
    </div>
  )
}
