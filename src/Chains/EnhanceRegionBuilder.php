<?php

namespace Noem\State\Chains;

use Noem\State\Middleware\Chain;
use Noem\State\RegionBuilder;

/**
 * @template-extends Chain<RegionBuilder,RegionBuilder>
 */
class EnhanceRegionBuilder extends Chain
{
    protected int $maxRestarts = -1;

    public function __construct()
    {
        parent::__construct(fn(RegionBuilder $b) => $b);
    }
}
