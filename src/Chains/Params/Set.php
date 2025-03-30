<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Region;

class Set implements AccessParams
{
    public function __construct(
        public Region $region,
        public string $key,
        public mixed $value,
        public ?string $state = null
    ) {
    }
}
