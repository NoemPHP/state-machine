<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains\Params;

use Noem\State\Chains\Params\Action;
use Noem\State\Feature\Loader\RegionSpawnRecord;

class SpawnRegionParams
{
    public function __construct(
        public readonly RegionSpawnRecord $record,
        public readonly Action $action
    ) {
    }
}
