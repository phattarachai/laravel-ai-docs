<?php

declare(strict_types=1);

namespace Phattarachai\AiDocs\Support;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * An inlined SVG standing in for the paragraph that held only its image.
 *
 * @see InlineSvg
 */
final class SvgFigure extends AbstractBlock
{
    /**
     * @param  string  $svg  sanitized markup, built by InlineSvg — never author text
     * @param  string  $ns  the figure's id prefix, which full screen re-namespaces
     */
    public function __construct(
        public readonly string $svg,
        public readonly string $label,
        public readonly string $ns,
        public readonly Keywords $keywords,
    ) {
        parent::__construct();
    }
}
