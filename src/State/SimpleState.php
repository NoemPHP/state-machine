<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\StateInterface;

class SimpleState implements StateInterface
{

    public function __construct(private string $id)
    {
    }

    public function equals(StateInterface $otherState): bool
    {
        return (string) $otherState === $this->id;
    }

    public function __toString()
    {
        return $this->id;
    }
}