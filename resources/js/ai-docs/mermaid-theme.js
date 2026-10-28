/** see README.md — "Mermaid" */

/**
 * Keep in sync with `.doc-root { font-family }` in ai-docs.css.
 *
 * It cannot be read from a custom property: mermaid measures every label in a
 * throwaway container under `<body>`, outside `.doc-root`, where a property
 * defined on `.doc-root` does not resolve. Mermaid's own default — Trebuchet —
 * carries no Thai glyphs, so Thai fell back to a face with taller ink than the
 * line box it was measured in and lost its below-vowels.
 */
const FONT = "'Instrument Sans', 'Noto Sans Thai', system-ui, sans-serif"

/**
 * Labels are HTML in a `<foreignObject>`, which clips whatever overflows it.
 * Mermaid sizes that box from a measure pass in its own container and then
 * hands the SVG to us, where `.doc-prose p` and friends restyle the same text —
 * bigger than the box it was cut for, so the tail of a line goes missing.
 *
 * These rules ship inside the SVG's own `<style>`, which travels with it, so
 * the measure pass and the final render agree on the numbers. `!important` is
 * load-bearing twice over: mermaid writes line-height and white-space inline,
 * and the host page's prose rules are more specific than a bare `.nodeLabel`.
 */
const LABEL_CSS = `
  foreignObject { overflow: visible; }
  foreignObject div,
  foreignObject span,
  foreignObject p {
    margin: 0 !important;
    font-family: ${FONT} !important;
    font-size: 16px !important;
    line-height: 1.7 !important;
  }
  foreignObject > div { white-space: normal !important; }`

const VARIABLES = {
  light: {
    background: '#ffffff',
    primaryColor: '#f3f4f7',
    primaryTextColor: '#1c1d21',
    primaryBorderColor: '#c5c8d0',
    secondaryColor: '#eaeef5',
    tertiaryColor: '#f8f9fb',
    lineColor: '#7d838f',
    textColor: '#1c1d21',
    clusterBkg: '#fafbfc',
    clusterBorder: '#dcdfe5',
    edgeLabelBackground: '#ffffff',
    titleColor: '#1c1d21',
  },
  soft: {
    background: '#ece9e1',
    primaryColor: '#e5e1d7',
    primaryTextColor: '#2b2a27',
    primaryBorderColor: '#c4bfb2',
    secondaryColor: '#dfdacd',
    tertiaryColor: '#f0ede6',
    lineColor: '#7f796d',
    textColor: '#2b2a27',
    clusterBkg: '#efece5',
    clusterBorder: '#d6d1c5',
    edgeLabelBackground: '#ece9e1',
    titleColor: '#2b2a27',
  },
  nord: {
    background: '#343b48',
    primaryColor: '#3b4252',
    primaryTextColor: '#e5e9f0',
    primaryBorderColor: '#4c566a',
    secondaryColor: '#434c5e',
    tertiaryColor: '#313744',
    lineColor: '#8a94a8',
    textColor: '#e5e9f0',
    clusterBkg: '#313744',
    clusterBorder: '#4c566a',
    edgeLabelBackground: '#343b48',
    titleColor: '#e5e9f0',
  },
  dark: {
    background: '#1b1c23',
    primaryColor: '#282a36',
    primaryTextColor: '#e7e8ed',
    primaryBorderColor: '#454859',
    secondaryColor: '#2f3240',
    tertiaryColor: '#22242e',
    lineColor: '#858ba1',
    textColor: '#e7e8ed',
    clusterBkg: '#1f212a',
    clusterBorder: '#363950',
    edgeLabelBackground: '#1b1c23',
    titleColor: '#e7e8ed',
  },
}

// The vocabulary a doc opts into with `class Node decision` or `Node:::decision`.
// Keep in sync with the `--doc-{ok,warn,bad,actor}-*` tokens in ai-docs.css, which
// hand-drawn SVGs use; `decision` is `warn` there.
const TONES = {
  light: {
    decision: ['#fef3c7', '#d97706', '#78350f'],
    ok: ['#dcfce7', '#16a34a', '#14532d'],
    bad: ['#fee2e2', '#dc2626', '#7f1d1d'],
    actor: ['#dbeafe', '#2563eb', '#1e3a8a'],
  },
  soft: {
    decision: ['#f6e8c3', '#c27c0e', '#6b3a0c'],
    ok: ['#d9eed9', '#2f8a46', '#1d4d2a'],
    bad: ['#f5dbd6', '#c9433a', '#6e211c'],
    actor: ['#dbe4f2', '#3466b8', '#1f3a6b'],
  },
  nord: {
    decision: ['#4a4536', '#ebcb8b', '#f3e2bd'],
    ok: ['#3a4540', '#a3be8c', '#d5e5c8'],
    bad: ['#4a3539', '#d57780', '#f0c4c8'],
    actor: ['#34495a', '#88c0d0', '#cfe6ee'],
  },
  dark: {
    decision: ['#3b2408', '#f59e0b', '#fde68a'],
    ok: ['#0b2f1a', '#22c55e', '#bbf7d0'],
    bad: ['#3d1113', '#ef4444', '#fecaca'],
    actor: ['#16244d', '#60a5fa', '#bfdbfe'],
  },
}

function tone(name, [fill, stroke, text]) {
  return `
    .node.${name} rect,
    .node.${name} polygon,
    .node.${name} circle,
    .node.${name} path {
      fill: ${fill} !important;
      stroke: ${stroke} !important;
      stroke-width: 1.4px !important;
    }
    .node.${name} .nodeLabel,
    .node.${name} .nodeLabel p,
    .node.${name} text {
      color: ${text} !important;
      fill: ${text} !important;
    }`
}

/** @param {'light'|'soft'|'nord'|'dark'} scheme */
export function themeFor(scheme) {
  const key = scheme in VARIABLES ? scheme : 'light'
  const tones = Object.entries(TONES[key])
    .map(([name, values]) => tone(name, values))
    .join('\n')

  return {
    startOnLoad: false,
    securityLevel: 'strict',
    // mermaid 12 defaults to ELK, which reorders a doc's flow away from source
    // order and fetches a ~1.4 MB layout chunk. A no-op on mermaid 11.
    layout: 'dagre',
    theme: 'base',
    // Also reaches the throwaway measure container and any plain SVG `<text>`,
    // which `LABEL_CSS` — scoped to `<foreignObject>` — does not.
    fontFamily: FONT,
    themeVariables: { ...VARIABLES[key], fontFamily: FONT, fontSize: '16px' },
    themeCSS: `
      ${LABEL_CSS}
      .edgePath path { stroke-width: 1.5px; }
      .cluster rect { rx: 8px; ry: 8px; }
      .node rect, .node polygon, .node circle, .node path { stroke-width: 1.2px; }
      .node polygon {
        fill: ${TONES[key].decision[0]};
        stroke: ${TONES[key].decision[1]};
      }
      .node polygon ~ * .nodeLabel,
      .node polygon ~ foreignObject .nodeLabel p {
        color: ${TONES[key].decision[2]};
      }
      ${tones}`,
    // `wrappingWidth` is the label's max-width, and mermaid's 200 is tight for a
    // sentence — a doc's nodes read as prose, not as one-word states.
    flowchart: {
      curve: 'basis',
      nodeSpacing: 44,
      rankSpacing: 54,
      padding: 12,
      wrappingWidth: 320,
      useMaxWidth: true,
    },
    sequence: { useMaxWidth: true },
  }
}
