<?php

namespace Noem\State\Feature\NamedEvents;

interface Event
{
    public function name(): string;
}
