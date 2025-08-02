<?php

declare(strict_types=1);

namespace Noem\State\Feature\OrthogonalRegions\RegionChains;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\Params\Connection;
use Noem\State\Region;

class ParentRegion
{
    public function __construct(private readonly ConnectedRegions $connectedRegions)
    {
    }

    //public function of(Region $childRegion): ?Region
    //{
    //    $list = ($this->connectedRegions)(new Connection($childRegion, false, 0));
    //    if (empty($list)) {
    //        return null;
    //    }
    //    if (count($list) > 2) {
    //        throw new \RuntimeException('There cannot be more than one parent of a region');
    //    }
    //
    //    return $list[0];
    //}
}
