/** see README.md — "Mermaid" */
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
const TONES = {
  light: {
    decision: ['#fef3c7', '#d97706', '#78350f'],
    ok: ['#dcfce7', '#16a34a', '#14532d'],
    bad: ['#fee2e2', '#dc2626', '#7f1d1d'],
    actor: ['#dbeafe', '#2563eb', '#1e3a8a'],
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

export function themeFor(scheme) {
  const key = scheme === 'dark' ? 'dark' : 'light'
  const tones = Object.entries(TONES[key])
    .map(([name, values]) => tone(name, values))
    .join('\n')

  return {
    startOnLoad: false,
    securityLevel: 'strict',
    theme: 'base',
    themeVariables: VARIABLES[key],
    themeCSS: `
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
    flowchart: { curve: 'basis', nodeSpacing: 44, rankSpacing: 54, padding: 12, useMaxWidth: true },
    sequence: { useMaxWidth: true },
  }
}
