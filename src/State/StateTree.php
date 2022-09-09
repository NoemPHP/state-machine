<?php

declare(strict_types=1);

namespace Noem\State\State;

use Iterator;
use Noem\State\HierarchicalStateInterface;
use Noem\State\Iterator\AscendingStateIterator;
use Noem\State\Iterator\DepthSortedStateIterator;
use Noem\State\Iterator\DescendingStateIterator;
use Noem\State\Iterator\ParallelDescendingIterator;
use Noem\State\NestedStateInterface;
use Noem\State\ParallelStateInterface;
use Noem\State\StateInterface;
use Noem\State\StateStorageInterface;

class StateTree
{
    use StateDepthTrait;

    /**
     * @var iterable<StateInterface>
     */
    private iterable $tree;

    /**
     * @var ?Iterator<StateInterface>
     */
    private ?Iterator $currentTreeByDepth = null;

    public function __construct(
        private StateInterface $state,
        private StateStorageInterface $stateStorage
    ) {
        if (!$state instanceof NestedStateInterface) {
            $this->tree = new \ArrayIterator([$state]);

            return;
        }
        $determineInitialSubState = \Closure::fromCallable([$this, 'determineInitialSubState']);
        $this->tree = new \CachingIterator(
            new ParallelDescendingIterator(
                new AscendingStateIterator(
                    DepthSortedStateIterator::getDeepestSubState($state, $determineInitialSubState)
                ),
                $determineInitialSubState
            )
        );
    }

    private function determineInitialSubState(NestedStateInterface $parent): ?StateInterface
    {
        try {
            $stored = $this->stateStorage->state($parent);
        } catch (\OutOfBoundsException $exception) {
            /**
             * Fallback behaviour: Check if there's an initial state configured
             * If not, return the first child
             */
            if ($parent instanceof HierarchicalStateInterface && $initial = $parent->initial()) {
                return $initial;
            }
            $children = $parent->children();
            if (count($children)) {
                return current($children);
            }

            return null;
        }

        return $stored;
    }

    public function findAncestorWithParallelParent(StateInterface $state): ?NestedStateInterface
    {
        if (!$state instanceof NestedStateInterface) {
            return null;
        }
        $lastState = $state;
        while ($state = $state->parent()) {
            if ($state instanceof ParallelStateInterface) {
                return $lastState;
            }
            $lastState = $state;
        }

        return null;
    }

    public function findByString(string $stateName): ?StateInterface
    {
        foreach ($this->tree as $state) {
            if ($state->equals($stateName)) {
                return $state;
            }
        }

        return null;
    }

    public function isInState(StateInterface $compareState): bool
    {
        foreach ($this->tree as $state) {
            if ($state->equals($compareState)) {
                return true;
            }
            //if ($state instanceof ParallelStateInterface && $state->isInState($compareState)) {
            //    return true;
            //}
        }

        return false;
    }

    public function existsInBranch(StateInterface $searchState): bool
    {
        foreach ($this->tree as $state) {
            if ($state->equals($searchState)) {
                return true;
            }
        }

        return false;
    }

    public function upwards(): iterable
    {
        if (!$this->currentTreeByDepth) {
            $this->currentTreeByDepth = new DepthSortedStateIterator($this->tree);
        }

        return $this->currentTreeByDepth;
    }
}
