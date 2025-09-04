<?php

namespace Noem\State\Chains;

use Noem\State\Chains\Params\Action;
use Noem\State\Chains\Params\Connection;
use Noem\State\Events;
use Noem\State\Middleware\Chain;
use Noem\State\Region;

/**
 * @template-extends Chain<Action,string>
 */
class DispatchAction extends Chain
{
    public function __construct(
        private readonly ConnectedRegions $connectedRegions,
        private readonly Events           $events,
    )
    {
        parent::__construct($this->onAction(...));
    }

    /**
     * @throws \Throwable
     */
    private function onAction(Action $action): string
    {


        /**
         * Process connected regions first.
         * This allows for nested states and transitions.
         */
        foreach ($connections = $this->connections($action->region) as $region) {
            $connectedAction = new Action($region, $action->payload);
            $this->call($connectedAction);
        }

        $this->events->onAction($action->region, $action->currentState, $action->payload);

        return $action->currentState;

    }

    private function connections(Region $region)
    {
        return $this->connectedRegions->call(
            new Connection($region)
        );
    }
}
