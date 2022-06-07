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
use Noem\State\StateInterface;

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

    public function __construct(StateInterface $state)
    {
        if (!$state instanceof NestedStateInterface) {
            $this->tree = new \ArrayIterator([$state]);

            return;
        }
        $this->tree = new \CachingIterator(
            new ParallelDescendingIterator(
                new AscendingStateIterator(DepthSortedStateIterator::getDeepestSubState($state)),
                function (HierarchicalStateInterface $parent, DescendingStateIterator $iterator) use ($state) {
                    $parentDepth = $this->getDepth($parent);
                    $childDepth = $this->getDepth($state);
                    if ($parentDepth > $childDepth) {
                        return $iterator->determineInitialSubState($parent);
                    }
                    $stateParent = $state;
                    while($stateParent->parent()){
                        if($stateParent->parent()->equals($parent)){
                            return $stateParent;
                        }
                        $stateParent=$stateParent->parent();
                    }
                    /**
                     * Original implementation below
                     * TODO: find a way to make it reusable. Decorator?
                     */
                    $initial = $parent->initial();
                    if ($initial) {
                        return $initial;
                    }
                    $children = $parent->children();
                    if (count($children)) {
                        return current($children);
                    }

                    return null;
                }
            )
        );
    }

    public function isInState(string|StateInterface $compareState): bool
    {
        foreach ($this->tree as $state) {
            if ($state->equals($compareState)) {
                return true;
            }
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
