<?php

namespace Noem\State;

#[\Attribute]
interface Hook
{
    public object $event {
        get;
    }
}
