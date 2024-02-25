<?php

declare(strict_types=1);

namespace Noem\State;

class StateMachine
{

    public function __construct(
        private Region $region,
        private ?Context $context = null
    ) {
        $this->context = $this->context ?? new Context();
    }

    public function trigger(object $payload): object
    {
        $this->region->trigger($payload);
    }
}
