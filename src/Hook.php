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
interface Hook
{
    public object $event {
        get;
    }
}
