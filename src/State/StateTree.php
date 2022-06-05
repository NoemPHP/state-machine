<?php

declare(strict_types=1);

namespace Noem\State\State;

use Iterator;
use Noem\State\Iterator\AscendingStateIterator;
use Noem\State\Iterator\DepthSortedStateIterator;
use Noem\State\Iterator\ParallelDescendingIterator;
use Noem\State\NestedStateInterface;
use Noem\State\StateInterface;

class StateTree
{
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
                new AscendingStateIterator(DepthSortedStateIterator::getDeepestSubState($state))
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

    public function upwards(): iterable
    {
        if (!$this->currentTreeByDepth) {
            $this->currentTreeByDepth = new DepthSortedStateIterator($this->tree);
        }

        return $this->currentTreeByDepth;
    }
}
