<?php

declare(strict_types=1);

namespace Noem\State\Transition;

use Noem\State\StateInterface;
use Noem\State\StateMachineInterface;

class EventEnabledTransition implements TransitionInterface
{
    public function __construct(private TransitionInterface $inner, private string $eventName)
    {
    }

    public function source(): StateInterface
    {
        return $this->inner->source();
    }

    public function target(): StateInterface
    {
        return $this->inner->target();
    }

    public function isEnabled(object $trigger, StateMachineInterface $stateMachine): bool
    {
        return $this->inner->isEnabled($trigger, $stateMachine) and $trigger instanceof $this->eventName;
    }
}
