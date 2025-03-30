<?php

declare(strict_types=1);

namespace Noem\State\Feature\Template\Compiler;

class Node
{

    public function __construct(
        public readonly NodeType $type,
        public readonly int $line,
        public readonly int $start,
        public readonly int $end,
        public readonly int $level,
        public readonly string $value,
    ) {
    }
}
