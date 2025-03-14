<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Region;

interface AccessParams
{
    public Region $region {
        get;
    }

    public string $key {
        get;
    }

    public ?string $state {
        get;
    }
}
