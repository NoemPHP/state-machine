<?php

declare(strict_types=1);

namespace Noem\State;

class Callback
{
    private object $newThis;

    public function __construct(
        public readonly \Closure $callback,
    ) {
        $this->newThis = new \stdClass();
    }

    public function __invoke(): mixed
    {
        set_exception_handler(function () {
        });
        $result = $this->callback->call($this->newThis);
        restore_error_handler();

        return $result;
    }

    public function bindTo(object $newThis)
    {
        $this->newThis = $newThis;
    }
}
