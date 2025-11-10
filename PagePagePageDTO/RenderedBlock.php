<?php
declare(strict_types=1);

namespace PagePagePageDTO;

final class RenderedBlock
{
    public function __construct(
        public readonly string $html,
        public readonly array $styles = [],
        public readonly array $scripts = [],
        public readonly array $assets = [] // array<array{file_id: string, filename: string}>,
    ) {
    }
}
