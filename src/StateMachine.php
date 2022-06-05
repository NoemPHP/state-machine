<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Observer\ActionObserver;
use Noem\State\Observer\EnterStateObserver;
use Noem\State\Observer\ExitStateObserver;
use Noem\State\Observer\StateMachineObserver;
use Noem\State\State\StateTree;
use Noem\State\Transition\TransitionProviderInterface;

class StateMachine implements ObservableStateMachineInterface, ActorInterface
{

    /**
     * The current state. Note that in hierarchical state machines,
     * any number of states can be active at the same time. So this really only represents
     * the origin of the current tree of active states
     *
     * @var StateInterface
     */
    private StateInterface $currentState;

    private \SplObjectStorage $trees;

    /**
     * @var StateMachineObserver[]
     */
    private array $observers = [];

    public function __construct(
        private TransitionProviderInterface $transitions,
        private StateStorageInterface $store
    ) {
        $this->trees = new \SplObjectStorage();
        $this->currentState = $this->store->state();
    }

    private function getTree(?StateInterface $state = null): StateTree
    {
        $state = $state ?? $this->currentState;
        if (!isset($this->trees[$state])) {
            $this->trees[$state] = new StateTree($state);
        }

        return $this->trees[$state];
    }

    public function attach(StateMachineObserver $observer): ObservableStateMachineInterface
    {
        $this->observers[] = $observer;

        return $this;
    }

    public function detach(StateMachineObserver $observer): ObservableStateMachineInterface
    {
        $key = array_search($observer, $this->observers, true);
        if ($key) {
            unset($this->observers[$key]);
        }

        return $this;
    }

    public function trigger(object $payload): StateMachineInterface
    {
        foreach ($this->getTree()->upwards() as $state) {
            $transition = $this->transitions->getTransitionForTrigger($state, $payload);
            if (!$transition) {
                continue;
            }
            $this->doTransition($state, $transition->target());

            return $this;
        }

        return $this;
    }

    private function doTransition(StateInterface $from, StateInterface $to)
    {
        $this->notifyExit($from, $to);
        $this->currentState = $to;
        $this->store->save($to);
        $this->notifyEnter($from, $to);
    }

    private function notifyExit(StateInterface $from, StateInterface $to): void
    {
        $newTree = $this->getTree($to);
        foreach ($this->getTree($from)->upwards() as $state) {
            if ($newTree->isInState($state)) {
                continue;
            }
            foreach ($this->observers as $observer) {
                if ($observer instanceof ExitStateObserver) {
                    $observer->onExitState($state, $this);
                }
            }
        }
    }

    private function notifyEnter(StateInterface $from, StateInterface $to): void
    {
        $oldTree = $this->getTree($from);
        foreach ($this->getTree($to)->upwards() as $state) {
            if ($oldTree->isInState($state)) {
                continue;
            }
            foreach ($this->observers as $observer) {
                if ($observer instanceof EnterStateObserver) {
                    $observer->onEnterState($state, $this);
                }
            }
        }
    }

    public function action(object $payload): object
    {
        foreach ($this->getTree()->upwards() as $state) {
            foreach ($this->observers as $observer) {
                if ($observer instanceof ActionObserver) {
                    $observer->onAction($state, $payload, $this);
                }
            }
        }

        return $payload;
    }

    /**
     * Check if the currently active state matches the specified one.
     * We are deliberately NOT giving out the actual state here and only allow asking for a comparison.
     *
     * This is because
     * 1.   Comparing the state externally no longer guarantees that compound states (hierarchical & parallel)
     *      are correctly taken into account
     * 2.   Handing out the state enables and facilitates moving stateful behaviour outside the state machine
     *      and into business logic
     *
     * Consequently, this method is not even part of the interface and its usage
     * is discouraged outside of testing & debugging scenarios.
     *
     * @param string|StateInterface $compareState
     *
     * @return bool
     */
    public function isInState(string|StateInterface $compareState): bool
    {
        return $this->getTree()->isInState($compareState);
    }
}
