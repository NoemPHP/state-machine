<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\LoaderChains;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Connection;
use Noem\State\Feature\Loader\LoaderChains\Params\SpawnRegionParams;
use Noem\State\Middleware\Chain;
use Noem\State\Region;
use Noem\State\Util\ParameterDeriver;

/**
 * @template-extends Chain<SpawnRegionParams,?Region>
 */
class SpawnRegion extends Chain
{

    public function __construct(
        private readonly ConnectedRegions $connectedRegions
    ) {
        parent::__construct($this->provider(...));
    }

    private function provider(SpawnRegionParams $params): ?Region
    {
        /**
         * Inspect the defined predicate function.
         * If it matches our trigger payload, then we can invoke it.
         */
        if (
            !ParameterDeriver::isCompatibleParameter(
                $params->record->guard,
                $params->action->payload
            )
        ) {
            return null;
        }
        if (!($params->record->guard)($params->action->payload)) {
            /**
             * Predicate returned false, so we bail
             */
            return null;
        }

        $currentState = $params->record->parentState;
        $subRegion = ($params->record->regionFactory)();
        /**
         * Create a Connection with a predicate that ties
         * the newly spawned region to its parent region/state
         */
        $connection = new Connection(
            $params->record->parentRegion,
            $subRegion,
            $params->record->connectionFlags,
            function (Connection $c) use (
                $currentState,
            ): bool {
                return $c->local->currentState() === $currentState;
            }
        );
        $this->connectedRegions->addConnection($connection);

        return $subRegion;
    }
}
