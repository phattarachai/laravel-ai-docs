<?php

declare(strict_types=1);

/*
 * UI copy for the docs panel, handed to the React module as a single `strings`
 * prop. Keys are the literal dotted strings used by `DEFAULT_STRINGS` in
 * resources/js/ai-docs/strings.js, so the two files diff at a glance.
 *
 * `callout.*` and `anchor.label` are rendered server-side into the markdown
 * cache, which is why that cache is keyed by locale.
 */

return [
    'nav.pages' => 'Pages',
    'nav.outline' => 'On this page',
    'nav.panels' => 'Sections',

    'scheme.toLight' => 'Switch to light',
    'scheme.toDark' => 'Switch to dark',

    'search.button' => 'Search docs…',
    'search.label' => 'Search docs',
    'search.loading' => 'Loading…',
    'search.hint' => 'Search every page — titles, headings and body text.',
    'search.noMatch' => 'No match for “:query”.',
    'search.close' => 'Esc',

    'page.empty' => 'No documentation pages yet.',
    'page.copyPath' => 'Copy :path',
    'page.pathCopied' => 'Path copied',
    'page.linkCopied' => 'Link copied',

    'zoom.open' => 'Full screen',
    'zoom.close' => 'Esc',

    'callout.note' => 'Note',
    'callout.tip' => 'Tip',
    'callout.important' => 'Important',
    'callout.warning' => 'Warning',
    'callout.caution' => 'Caution',

    'anchor.label' => 'Link to this section',
];
