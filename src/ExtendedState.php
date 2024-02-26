<?php

namespace Noem\State;

class ExtendedState
{

    public function __construct(private Context $context)
    {
    }

    public function __get(string $key)
    {
        $this->context->getFromStack($key);
    }

    public function __set(string $key, mixed $value)
    {
    }
}
