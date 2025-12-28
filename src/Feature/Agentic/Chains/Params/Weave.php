<?php

declare(strict_types=1);

namespace Noem\State\Feature\Agentic\Chains\Params;

use Noem\State\Region;

/**
 * Parameters for Weave chain invocation
 */
class Weave
{
    public function __construct(
        public readonly Region $region,
        public readonly string $intent,
        public readonly array $options = [],
    ) {
    }
}
