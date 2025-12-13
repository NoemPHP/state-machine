<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

class Callback
{
    public \Noem\State\Region $region;

    /**
     * @var \Closure
     */
    public \Closure $handler;

    public object $trigger;

    public string $event;

    public string $state;

    public function __construct(
        \Noem\State\Region $region,
        \Closure $handler,
        object $trigger,
        string $event = '',
        string $state = ''
    ) {
        $this->handler = $handler;
        $this->trigger = $trigger;
        $this->region = $region;
        $this->event = $event;
        $this->state = $state;
    }
}
