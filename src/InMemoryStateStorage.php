<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\State\RootState;

class InMemoryStateStorage implements StateStorageInterface
{

    private array $storage = [];

    private RootState $root;

    public function __construct(private StateInterface $state)
    {
        $this->root = new RootState();
        $this->save($this->state); //TODO initialize nested states
    }

    public function state(StateInterface $parent = null): StateInterface
    {
        $key = (string)($parent ?? $this->root);
        if (!isset($this->storage[$key])) {
            throw new \OutOfBoundsException("Saved state not found for parent '{$key}'");
        }

        return $this->storage[$key];
    }

    public function save(StateInterface $stateConfiguration, StateInterface $parent = null): void
    {
        $this->storage[(string)($parent ?? $this->root)] = $stateConfiguration;
    }
}
