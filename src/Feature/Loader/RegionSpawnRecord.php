<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

use Closure;
use Noem\State\Connection as C;
use Noem\State\Region;

class RegionSpawnRecord
{
    /**
     * @param Region $parentRegion
     * @param string $parentState
     * @param Closure():Region $regionFactory
     * @param Closure():bool $guard
     * @param int $connectionFlags
     */
    public function __construct(
        public readonly Region $parentRegion,
        public readonly string $parentState,
        public readonly Closure $regionFactory,
        public readonly Closure $guard,
        public int $connectionFlags = C::DYNAMIC | C::RECEIVE_EVENTS | C::RECEIVE_ACTIONS,
    ) {
    }
}
