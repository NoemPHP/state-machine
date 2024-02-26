<?php

namespace Noem\State;

class Context
{

    /**
     * @var iterable<Region>
     */
    private \SplQueue $regionStack;

    private array $data = [];

    public readonly ExtendedState $extendedState;

    public function __construct()
    {
        $this->regionStack = new \SplQueue();
        $this->extendedState = new ExtendedState($this);
        $this->history = new History($this);
    }

    public function push(Region $region)
    {
        $this->regionStack->push($region);
    }

    public function pop()
    {
        return $this->regionStack->pop();
    }

    public function getFromStack(string $key): mixed
    {
        foreach ($this->regionStack as $item) {
            if (!$item->isInheritedKey($key)) {
                continue;
            }

            return $this->data[$key] ?? null;
        }
    }

    public function setOnStack(string $key, mixed $value): mixed
    {
        foreach ($this->regionStack as $item) {
            if (!$item->isInheritedKey($key)) {
                continue;
            }

            return $this->data[$key] = $value;
        }
    }
}
