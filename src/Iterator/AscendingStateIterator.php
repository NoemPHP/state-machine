<?php

declare(strict_types=1);

namespace Noem\State\Iterator;

use Noem\State\NestedStateInterface;

/**
 * Recursively yields all parents of the origin state
 */
class AscendingStateIterator implements \Iterator
{

    private ?NestedStateInterface $current;

    public function __construct(private NestedStateInterface $state)
    {
        $this->current = $this->state;
    }

    public function current(): NestedStateInterface
    {
        return $this->current;
    }

    public function next()
    {
        $this->current = $this->current->parent();
    }

    public function key(): string
    {
        return (string) $this->current;
    }

    public function valid(): bool
    {
        return $this->current instanceof NestedStateInterface;
    }

    public function rewind()
    {
        $this->current = $this->state;
    }
}