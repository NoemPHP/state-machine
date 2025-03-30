<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

class Action
{
    private(set) string $currentState;

    public object $payload;


    public function __construct(string $currentState, object $payload)
    {
        $this->currentState = $currentState;
        $this->payload = $payload;
    }
}
