<?php

declare(strict_types=1);

namespace Noem\State\Transition;

use Noem\State\StateInterface;

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

    public function isEnabled(object $trigger): bool
    {
        return $this->inner->isEnabled($trigger) and $trigger instanceof $this->eventName;
    }
}
