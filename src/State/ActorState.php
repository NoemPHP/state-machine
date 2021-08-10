<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\StatefulActorInterface;
use Noem\State\StateInterface;

class ActorState implements StateInterface, StatefulActorInterface
{

    /**
     * @var callable[]
     */
    private array $actions;

    public function __construct(private StateInterface $inner, callable ...$actions)
    {
        $this->actions = $actions;
    }

    public function equals(StateInterface $otherState): bool
    {
        return $this->inner->equals($otherState);
    }

    public function __toString()
    {
        return (string) $this->inner;
    }

    public function action(object $payload): void
    {
        foreach ($this->actions as $action) {
        }
    }
}