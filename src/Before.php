<?php

namespace Noem\State;

/**
 * phpcs 3.11.3 trips over the property hooks here.
 * Temporarily disable them
 * TODO: Enable sniffs again after a major update
 * @phpcs:disable Internal.ParseError.InterfaceHasMemberVar
 * @phpcs:disable PSR2.Classes.PropertyDeclaration.Multiple
 * @phpcs:disable PSR2.Classes.PropertyDeclaration.ScopeMissing
 */
class Before implements Hook
{
    private object $internalEvent;
    public object $event {
        get {
            return $this->internalEvent;
        }
    }

    public static function fromEvent(object $event): self
    {
        $self = new self();
        $self->internalEvent = $event;
        return $self;
    }
}
