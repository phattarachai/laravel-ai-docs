import { useStrings } from './strings'

/** see README.md — "Full screen" */
export function DocZoom({ html, onClose }) {
  const t = useStrings()

  return (
    <div className="doc-zoomer" role="dialog" aria-modal="true" aria-label={t('zoom.open')}>
      <div className="doc-zoomer-bar">
        <button type="button" className="doc-zoomer-close" onClick={onClose}>
          {t('zoom.close')}
        </button>
      </div>
      <div
        className="doc-zoomer-body doc-prose"
        onClick={(event) => event.target.tagName === 'IMG' && onClose()}
        dangerouslySetInnerHTML={{ __html: html }}
      />
    </div>
  )
}
