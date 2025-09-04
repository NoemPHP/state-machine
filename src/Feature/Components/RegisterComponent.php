<?php

namespace Noem\State\Feature\Components;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;

class RegisterComponent implements BuildStep
{
    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        $region = $next($builder);
        return $region;
    }
}
