<?php

declare(strict_types=1);

namespace Noem\State\Feature\ExtendedState\ContextChains\Params;

use Noem\State\Region;

class BoundAccessParams
{
    public const TYPE_PROPERTY = 0;
    public const TYPE_METHOD = 1;

    public function __construct(
        public readonly Region $region,
        public readonly int $type,
        public readonly string $name,
        public readonly mixed $payload = null,
    ) {
    }
}
