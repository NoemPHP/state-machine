<?php

declare(strict_types=1);

namespace Noem\State;

use Iterator;
use Noem\State\Iterator\AscendingStateIterator;
use Noem\State\Iterator\DepthSortedStateIterator;
use Noem\State\Iterator\ParallelDescendingIterator;
use Noem\State\Observer\ActionObserver;
use Noem\State\Observer\EnterStateObserver;
use Noem\State\Observer\ExitStateObserver;
use Noem\State\Observer\StateMachineObserver;
use Noem\State\Transition\TransitionProviderInterface;

class StateMachine implements ObservableStateMachineInterface, ActorInterface
{

    /**
     * @var iterable<StateInterface>
     */
    private iterable $currentTree;

    /**
     * @var StateMachineObserver[]
     */
    private array $observers = [];

    /**
     * @var ?Iterator<StateInterface>
     */
    private ?Iterator $currentTreeByDepth = null;

    public function __construct(
        private TransitionProviderInterface $transitions,
        private StateStorageInterface $store
    ) {
        $this->initializeTreeIterator($this->store->state());
    }

    private function initializeTreeIterator(StateInterface $state)
    {
        if (!$state instanceof NestedStateInterface) {
            $this->currentTree = new \ArrayIterator([$state]);

            return;
        }
        $this->currentTree = new \CachingIterator(
            new ParallelDescendingIterator(
                new AscendingStateIterator($state)
            )
        );
        $this->currentTreeByDepth = null;
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
        foreach ($this->getTreeByDepth() as $state) {
            $transition = $this->transitions->getTransitionForTrigger($state, $payload);
            if (!$transition) {
                continue;
            }
            $this->doTransition($state, $transition->target());

            return $this;
        }

        return $this;
    }

    private function getTreeByDepth(): iterable
    {
        if (!$this->currentTreeByDepth) {
            $this->currentTreeByDepth = new DepthSortedStateIterator($this->currentTree);
        }

        return $this->currentTreeByDepth;
    }

    private function doTransition(StateInterface $from, StateInterface $to)
    {
        $this->notifyExit($from);
        $this->initializeTreeIterator($to);
        $this->store->save($to);
        $this->notifyEnter($to);
    }

    private function notifyExit(StateInterface $state): void
    {
        foreach ($this->observers as $observer) {
            if ($observer instanceof ExitStateObserver) {
                $observer->onExitState($state, $this);
            }
        }
    }

    private function notifyEnter(StateInterface $state): void
    {
        foreach ($this->observers as $observer) {
            if ($observer instanceof EnterStateObserver) {
                $observer->onEnterState($state, $this);
            }
        }
    }

    public function action(object $payload): object
    {
        foreach ($this->getTreeByDepth() as $state) {
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
        foreach ($this->currentTree as $state) {
            if ($state->equals($compareState)) {
                return true;
            }
        }

        return false;
    }
}
