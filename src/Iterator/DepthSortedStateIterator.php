<?php

declare(strict_types=1);

namespace Noem\State\Iterator;

use Noem\State\HierarchicalStateInterface;
use Noem\State\NestedStateInterface;
use Noem\State\StateInterface;

/**
 * Passes on the contents of another StateIterator, but sorts all states by their depth
 * Please note that an array conversion cannot be avoided for this to work, so you need to walk the entire tree
 * at least once. Keep this in mind when using this with very large state graphs
 */
class DepthSortedStateIterator extends \ArrayIterator
{
    /**
     * @param \Iterator<StateInterface> $stateIterator
     */
    public function __construct(\Iterator $stateIterator)
    {
        $array = iterator_to_array($stateIterator);
        uasort($array, [$this, 'sort']);
        parent::__construct($array);
    }

    private function sort(StateInterface $a, StateInterface $b): int
    {
        $depthA = $this->getDepth($a);
        $depthB = $this->getDepth($b);

        return $depthB <=> $depthA;
    }

    private function getDepth(StateInterface $state): int
    {
        if (!$state instanceof NestedStateInterface) {
            return 0;
        }
        $i = 0;
        while ($state = $state->parent()) {
            $i++;
        }

        return $i;
    }

    public static function getDeepestSubState(
        NestedStateInterface $state,
        ?callable $determineInitialSubState = null
    ): NestedStateInterface {
        if (!$state instanceof HierarchicalStateInterface) {
            return $state;
        }
        $sorted =
            new self(
                new ParallelDescendingIterator(
                    new DescendingStateIterator(
                        $state,
                        $determineInitialSubState
                    ),
                    $determineInitialSubState
                )
            );

        return $sorted->current();
    }
}
