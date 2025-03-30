<?php

declare(strict_types=1);

namespace Noem\State\Feature\Ai;

class ResponseFormat
{
    public function __construct(
        public readonly string $format,
        public readonly array $definition
    ) {
    }
}
