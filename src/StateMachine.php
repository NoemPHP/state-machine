<?php

declare(strict_types=1);

namespace Noem\State;

class StateMachine
{
    public function __construct(
        private Region $region,
    ) {
    }

    public function trigger(object $payload): object
    {
        $this->region->trigger($payload);
    }
}
