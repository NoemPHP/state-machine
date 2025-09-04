<?php

namespace Noem\State\Feature\Components;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;

class AddComponentToState implements BuildStep
{

    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        // TODO: Implement callback() method.
    }
}