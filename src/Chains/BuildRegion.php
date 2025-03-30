<?php

namespace Noem\State\Chains;

use Noem\State\Middleware\Chain;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * @template-extends Chain<RegionBuilder,Region>
 */
class BuildRegion extends Chain
{
    protected int $maxRestarts = -1;
}
