<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

use Noem\State\Connection as C;
use Noem\State\Region;

class RegionSpawnRecord
{
    public function __construct(
        public readonly Region $parentRegion,
        public readonly string $parentState,
        public readonly array $definition,
        public readonly \Closure $guard,
        public int $connectionFlags = C::DYNAMIC | C::RECEIVE_EVENTS | C::RECEIVE_ACTIONS | C::RECEIVE_META,
    ) {
    }
}
