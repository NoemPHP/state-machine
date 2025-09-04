<?php

namespace Noem\State\Feature\Transitions;

use Noem\State\Region;

class TransitionRegistry
{
    /**
     * @var \SplObjectStorage<Region,array>
     */
    private \SplObjectStorage $transitionsByRegion;

    public function __construct()
    {
        $this->transitionsByRegion = new \SplObjectStorage();
    }

    public function pushTransition(Region $region, string $from, string $to, ?\Closure $guard = null): void
    {
        $this->ensureRegionStorage($region, $from);
        $transitions = $this->transitionsByRegion[$region];
        $transitions[$from][$to][] = $guard ?? fn(object $t): bool => true;
        $this->transitionsByRegion[$region] = $transitions;
    }

    public function getTransitionsForState(Region $region, string $state): array
    {
        $this->ensureRegionStorage($region, $state);
        $transitions = $this->transitionsByRegion[$region];
        return $transitions[$state];
    }

    private function ensureRegionStorage(Region $region, string $from): void
    {
        if (!isset($this->transitionsByRegion[$region])) {
            $this->transitionsByRegion[$region] = [];
        }
        $transitions = $this->transitionsByRegion[$region];
        if (!array_key_exists($from, $transitions)) {
            $transitions[$from] = [];
        }
        $this->transitionsByRegion[$region] = $transitions;
    }
}
