<?php

namespace Noem\State\Chains;

use Noem\State\Chains\Params\RegionConstructor;
use Noem\State\Middleware\Chain;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * @template-extends Chain<Region,Region>
 */
class EnhanceRegion extends Chain
{

    protected int $maxRestarts = -1;

    public function __construct()
    {
        parent::__construct(fn(RegionBuilder $b) => $b);
    }
}
