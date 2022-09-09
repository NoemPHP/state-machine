<?php

declare(strict_types=1);

namespace Noem\State\Iterator;

use Noem\State\HierarchicalStateInterface;
use Noem\State\ParallelStateInterface;

/**
 * Passes through the given Iterator, but recursively descends into all children of a ParallelStateInterface
 * Recursion means: Any nested ParallelStateInterface encountered when descending is also processed
 */
class ParallelDescendingIterator implements \Iterator
{
    /**
     * @var callable|null
     */
    private $determineInitialSubState = null;

    private ?\Iterator $currentDescent;

    public function __construct(private \Iterator $stateIterator, ?callable $determineInitialSubState = null)
    {
        $this->determineInitialSubState = $determineInitialSubState;
        $this->rewind();
    }

    public function current(): mixed
    {
        if ($this->currentDescent && $this->currentDescent->valid()) {
            return $this->currentDescent->current();
        }

        return $this->stateIterator->current();
    }

    public function next(): void
    {
        if (!$this->currentDescent && $this->stateIterator->current() instanceof ParallelStateInterface) {
            $appendIterator = new \AppendIterator();
            foreach ($this->stateIterator->current()->children() as $child) {
                $appendIterator->append(
                    new DescendingStateIterator(
                        $child,
                        $this->determineInitialSubState
                    )
                );
            }

            $this->currentDescent = new self($appendIterator, $this->determineInitialSubState);

            return;
        }
        if ($this->currentDescent) {
            if ($this->currentDescent->valid()) {
                $this->currentDescent->next();

                return;
            }
            $this->currentDescent = null;
        }

        $this->stateIterator->next();
    }

    public function key(): string
    {
        if ($this->currentDescent && $this->currentDescent->valid()) {
            return $this->currentDescent->key();
        }

        return $this->stateIterator->key();
    }

    public function valid(): bool
    {
        return $this->stateIterator->valid();
    }

    public function rewind(): void
    {
        $this->stateIterator->rewind();
        $this->currentDescent = null;
    }
}
