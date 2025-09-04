<?php

declare(strict_types=1);

namespace Noem\State\Feature\Transitions\Chains\Params;

class Guard
{
    /**
     * @var callable
     */
    public $handler;

    public function __construct(
        public(set) \Noem\State\Region $region,
        private(set) readonly string $origin,
        private(set) readonly string $target,
        callable $handler,
        public object $trigger
    ) {
        $this->handler = $handler;
    }
}
