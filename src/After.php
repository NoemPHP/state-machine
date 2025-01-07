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

    public static function fromEvent(object $event): self
    {
        $self = new self();
        $self->__event = $event;
        return $self;
    }
}