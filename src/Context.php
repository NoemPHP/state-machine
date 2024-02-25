<?php

namespace Noem\State;

class Context
{

    /**
     * @var iterable<Region>
     */
    private \SplQueue $stack;

    private array $data = [];

    public readonly ExtendedState $extendedState;

    public function __construct()
    {
        $this->stack = new \SplQueue();
        $this->stack->setIteratorMode(\SplDoublyLinkedList::IT_MODE_LIFO);
        $this->extendedState = new ExtendedState($this);
        $this->history = new History($this);
    }

    public function push(Region $region)
    {
        $this->stack->push($region);
    }

    public function pop()
    {
        return $this->stack->pop();
    }

    public function getFromStack(string $key): mixed
    {
        foreach ($this->stack as $item) {
            if (!$item->references($key)) {
                continue;
            }

            return $this->data[$key] ?? null;
        }
    }
}
