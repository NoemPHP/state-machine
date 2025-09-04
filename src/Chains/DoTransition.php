<?php

namespace Noem\State\Chains;

use Noem\State\Chains\Params\Connection;
use Noem\State\Chains\Params\Transition;
use Noem\State\Events;
use Noem\State\Middleware\Chain;
use Noem\State\Region;

/**
 * Carries out a transition.
 * Returns true if the transition has been executed successfully, false on error.
 * @template-extends Chain<Transition,bool>
 */
class DoTransition extends Chain
{
    public function __construct(
        private readonly ConnectedRegions $connectedRegions,
        private readonly Events           $events,
    )
    {
        parent::__construct($this->doTransition(...));
    }

    /**
     * @throws \Throwable
     */
    private function doTransition(Transition $transition)
    {
//        if ($action->region->isFinal()) {
//            return $action->currentState;
//        }
        $this->events->onExitState($transition->region, $transition->previousState, $transition->payload);
        foreach ($this->connections($transition->region) as $region) {
            $region->onEnterParent($transition->payload);
        }
        $this->events->onEnterState($transition->region, $transition->currentState, $transition->payload);
    }

    private function connections(Region $region): iterable
    {
        return $this->connectedRegions->call(
            new Connection($region)
        );
    }
}
