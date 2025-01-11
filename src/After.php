<?php

namespace Noem\State;

#[\Attribute]
class After implements Hook
{
    private object $__event;

    public object $event {
        get {
            return $this->__event;
        }
    }

    public function __construct(?object $event = null)
    {
        $this->__event = $event ?? new \stdClass();
    }

    public static function fromEvent(object $event): self
    {
        $self = new self($event);
        return $self;
    }
}
