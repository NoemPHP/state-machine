<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\MetaType;
use Noem\State\Region;

class Meta
{
    public function __construct(
        public Region $region,
        public readonly MetaType $type,
    ) {
    }
}
