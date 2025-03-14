<?php

namespace Noem\State\Feature\NamedEvents;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Name
{
    public function __construct(public string $eventName)
    {
    }
}
