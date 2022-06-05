<?php

declare(strict_types=1);

namespace Noem\State\Transition;

use Noem\State\StateInterface;

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

    public function isEnabled(object $trigger): bool
    {
        return true;
    }
}
