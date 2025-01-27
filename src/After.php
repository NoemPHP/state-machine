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
#[\Attribute]
class After implements Hook
{
    private object $internalEvent;

    public object $event {
        get {
            return $this->internalEvent;
        }
    }

    public function __construct(?object $event = null)
    {
        $this->internalEvent = $event ?? new \stdClass();
    }

    public static function fromEvent(object $event): self
    {
        $self = new self($event);
        return $self;
    }
}
