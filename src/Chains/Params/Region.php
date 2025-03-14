<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Region as RegionObject;

class Region
{

    public function __construct(public readonly RegionObject $region)
    {
    }
}
