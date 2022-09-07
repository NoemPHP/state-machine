<?php

declare(strict_types=1);

namespace Noem\State\Transition;

use Noem\State\StateInterface;
use Noem\State\StateMachineInterface;

class SimpleTransition implements TransitionInterface
{
    public function __construct(
        private StateInterface $source,
        private StateInterface $target
    ) {
    }

    public function source(): StateInterface
    {
        return $this->source;
    }

    public function target(): StateInterface
    {
        return $this->target;
    }

    public function isEnabled(object $trigger, StateMachineInterface $stateMachine): bool
    {
        return true;
    }
}
