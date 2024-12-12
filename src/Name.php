<?php

namespace Noem\State;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Name
{
    public function __construct(public string $eventName)
    {
    }
}
