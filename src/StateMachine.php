<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Iterator\AscendingStateIterator;
use Noem\State\Iterator\ParallelDescendingIterator;
use Noem\State\Observer\ActionObserver;
use Noem\State\Observer\EnterStateObserver;
use Noem\State\Observer\ExitStateObserver;
use Noem\State\Observer\StateMachineObserver;
use Noem\State\State\SimpleState;
use Noem\State\Transition\TransitionProviderInterface;

class StateMachine implements ObservableStateMachineInterface, StatefulActorInterface
{

    private \Iterator $currentTree;

    /**
     * @var StateMachineObserver[]
     */
    private array $observers = [];

    public function __construct(
        private TransitionProviderInterface $transitions,
        private StateStorageInterface $store
    ) {
        $this->initializeTreeIterator($this->store->state());
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

    private function notifyEnter(StateInterface $state): void
    {
        foreach ($this->observers as $observer) {
            if ($observer instanceof EnterStateObserver) {
                $observer->onEnterState($state, $this);
            }
        }
    }

    private function notifyExit(StateInterface $state): void
    {
        foreach ($this->observers as $observer) {
            if ($observer instanceof ExitStateObserver) {
                $observer->onExitState($state, $this);
            }
        }
    }

    public function trigger(object $payload): StateMachineInterface
    {
        foreach ($this->currentTree as $state) {
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
        $this->notifyExit($from);
        $this->initializeTreeIterator($to);
        $this->store->save($to);
        $this->notifyEnter($to);
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
    }

    public function action(object $payload): object
    {
        foreach ($this->currentTree as $state) {
            foreach ($this->observers as $observer) {
                if ($observer instanceof ActionObserver) {
                    $observer->onAction($state, $payload, $this);
                }
            }
        }

        return $payload;
    }

    public function isInState(string|StateInterface $compareState): bool
    {
        //TODO This needs to go
        $compareState = is_string($compareState)
            ? new SimpleState($compareState)
            : $compareState;

        foreach ($this->currentTree as $state) {
            if ($state->equals($compareState)) {
                return true;
            }
        }

        return false;
    }
}
