<?php

declare(strict_types=1);

namespace Noem\State\Iterator;

use Noem\State\HierarchicalStateInterface;
use Noem\State\NestedStateInterface;
use Noem\State\StateInterface;

/**
 * Yields all active descendant states of the given origin state
 */
class DescendingStateIterator implements \Iterator
{
    /**
     * @var callable(StateInterface):StateInterface|null
     */
    private $determineInitialSubState;

    private ?NestedStateInterface $current;

    /**
     * DescendingStateIterator constructor.
     *
     * @param HierarchicalStateInterface $state
     * @param null|callable(StateInterface, DescendingStateIterator):StateInterface|null $determineInitialSubState
     */
    public function __construct(
        private NestedStateInterface $state,
        ?callable $determineInitialSubState = null
    ) {
        $this->determineInitialSubState = $determineInitialSubState ?? [$this, 'determineInitialSubState'];
        $this->rewind();
    }

    public function current(): NestedStateInterface
    {
        return $this->current;
    }

    public function next(): void
    {
        $this->current = ($this->determineInitialSubState)($this->current, $this);
    }

    public function key(): string
    {
        return (string) $this->current;
    }

    public function valid(): bool
    {
        return $this->current instanceof NestedStateInterface;
    }

    public function rewind(): void
    {
        $this->current = $this->state;
    }

    public function determineInitialSubState(NestedStateInterface $parent): ?StateInterface
    {
        if ($parent instanceof HierarchicalStateInterface && $initial = $parent->initial()) {
            return $initial;
        }
        $children = $parent->children();
        if (count($children)) {
            return current($children);
        }

        return null;
    }
}
