<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Context\Context;
use Noem\State\Context\ContextProviderInterface;
use Noem\State\Context\EmptyContextProvider;
use Noem\State\Exception\StateMachineExceptionInterface;
use Noem\State\Observer\ActionObserver;
use Noem\State\Observer\EnterStateObserver;
use Noem\State\Observer\ExitStateObserver;
use Noem\State\Observer\StateMachineObserver;
use Noem\State\State\StateTree;
use Noem\State\Transition\TransitionProviderInterface;

class StateMachine implements ObservableStateMachineInterface, ContextAwareStateMachineInterface, ActorInterface
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

    private bool $isTransitioning = false;

    /**
     * @var \SplObjectStorage<StateInterface, ImmutableContextInterface>
     */
    private \SplObjectStorage $contextMap;

    /**
     * @var StateMachineObserver[]
     */
    private array $observers = [];

    public function __construct(
        private readonly TransitionProviderInterface $transitions,
        private readonly StateStorageInterface $store,
        private readonly ?object $initialTrigger = null,
        private readonly ContextProviderInterface $contextProvider = new EmptyContextProvider()
    ) {
        $this->trees = new \SplObjectStorage();
        $this->currentState = $this->store->state();
        $this->contextMap = new \SplObjectStorage();
        $this->updateContexts($this->getTree(), $this->initialTrigger ?? new \stdClass());
    }

    private function getTree(?StateInterface $state = null): StateTree
    {
        $state = $state ?? $this->currentState;
        if (!isset($this->trees[$state])) {
            $this->trees[$state] = new StateTree($state, $this->store);
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
        if ($this->isTransitioning) {
            throw new class ('State machine is currently transitioning') extends \RuntimeException implements
                StateMachineExceptionInterface
            {
            };
        }
        $this->isTransitioning = true;
        foreach ($this->getTree()->upwards() as $state) {
            $transition = $this->transitions->getTransitionForTrigger($state, $payload, $this);
            if (!$transition) {
                continue;
            }
            $this->doTransition($state, $transition->target(), $payload);
            break;
        }

        $this->isTransitioning = false;

        return $this;
    }

    private function doTransition(StateInterface $from, StateInterface $to, object $payload)
    {
        $this->notifyExit($from, $to);

        $newTree = $this->getTree($to);
        if ($immediateChildOfParallelState = $newTree->findAncestorWithParallelParent($to)) {
            $this->store->save($to, $immediateChildOfParallelState);
            $this->currentState = $immediateChildOfParallelState->parent();
            unset($this->trees[$to]); // Tree cache is stale now
        } else {
            $this->store->save($to);
            $this->currentState = $to;
        }
        $this->updateContexts($newTree, $payload);
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

    private function updateContexts(StateTree $tree, object $trigger)
    {
        foreach ($tree->upwards() as $state) {
            if (!$this->contextMap->offsetExists($state)) {
                $newContext = $this->contextProvider->createContext($state, $trigger);
            } else {
                $newContext = $this->contextMap[$state]->withTrigger($trigger);
            }
            $this->contextMap[$state] = $newContext;
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
        $stateTree = $this->getTree();
        if (is_string($compareState)) {
            $compareState = $stateTree->findByString($compareState);
            if (!$compareState) {
                return false;
            }
        }

        return $stateTree->isInState($compareState);
    }

    public function context(StateInterface $state): ContextInterface
    {
        return $this->contextMap[$state];
    }
}
