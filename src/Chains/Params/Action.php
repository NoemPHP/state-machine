<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

class Action
{

    public string $currentState {
        get {
            return $this->region->currentState();
        }
    }

    public object $payload;

    public function __construct(public readonly \Noem\State\Region $region, object $payload)
    {
        $this->payload = $payload;
    }
}
