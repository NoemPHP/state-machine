<?php

declare(strict_types=1);

namespace Noem\State;

class InMemoryStateStorage implements StateStorageInterface
{
    public function __construct(private StateInterface $state)
    {
    }

    public function state(): StateInterface
    {
        return $this->state;
    }

    public function save(StateInterface $stateConfiguration): void
    {
        $this->state = $stateConfiguration;
    }
}
