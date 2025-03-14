<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Events;
use Noem\State\Chains;

class RegionConstructor
{

    public function __construct(
        public array $states,
        public array $transitions,
        public Events $events,
        public ?string $initial,
        public ?string $final,
        public Chains\DispatchAction $actionChain,
        public Chains\Guard $guardChain,
        public Chains\ConnectedRegions $connectionsChain,
        public Chains\Path $path,
    ) {
    }
}
