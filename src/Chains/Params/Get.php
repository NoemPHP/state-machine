<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Region;

class Get implements AccessParams
{

    public function __construct(
        public Region $region,
        public readonly string $key,
        public ?string $state = null
    ) {
    }
}
