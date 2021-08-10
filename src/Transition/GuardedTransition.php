<?php

declare(strict_types=1);

namespace Noem\State\Transition;

use Noem\State\StateInterface;

class GuardedTransition implements TransitionInterface
{

    /**
     * @var callable(object, TransitionInterface):bool
     */
    private $guard;

    /**
     * GuardedTransition constructor.
     *
     * @param TransitionInterface $inner
     * @param callable(object, TransitionInterface):bool $guard
     */
    public function __construct(private TransitionInterface $inner, callable $guard)
    {
        $this->guard = $guard;
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
        return $this->inner->isEnabled($trigger) and ($this->guard)($trigger, $this);
    }
}