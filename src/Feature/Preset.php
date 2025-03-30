<?php

declare(strict_types=1);

namespace Noem\State\Feature;

use Noem\State\Feature\EventHooks\EventHooks;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;

class Preset
{
    public function default(RegionBuilder $builder): void
    {
        foreach (
            [
                new RegionLoader(),
                new EventHooks(),
            ] as $feature
        ) {
            $feature($builder);
        }
    }
}
