<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Chains\Params\Connection;
use Noem\State\Middleware\Chain;
use Noem\State\Region;

/**
 * @template-extends Chain<Region,string>
 *     Returns a joined path of all hierarchical states by recursively querying parent regions.
 */
class Path extends Chain
{
    public function __construct(private readonly ConnectedRegions $connectedRegions)
    {
        parent::__construct(fn(Region $r) => $r->currentState());

        $this->link(function (Region $region, callable $next) {
            $base = $next($region);
            $parent = $this->parent($region);
            while ($parent) {
                $base = $parent->currentState() . '/' . $base; // Prepend the parent's ID to the base path
                $parent = $this->parent($parent); // Move up to the next parent
            }
            return $base;
        });
    }

    public function parent(Region $childRegion): ?Region
    {
        $list = $this->connectedRegions->call(new Connection($childRegion, false, 0));
        if (empty($list)) {
            return null;
        }
        if (count($list) > 2) {
            throw new \RuntimeException('There cannot be more than one parent of a region');
        }

        return $list[0];
    }
}
