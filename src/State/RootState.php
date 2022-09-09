<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\StateInterface;

class RootState implements StateInterface
{

    public function equals(StateInterface|string $otherState): bool
    {
        return (string)$this === (string)$otherState;
    }

    public function __toString()
    {
        return '@@root';
    }
}
