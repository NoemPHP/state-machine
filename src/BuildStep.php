<?php

namespace Noem\State;

interface BuildStep
{
    public function callback(RegionBuilder $builder, callable $next, callable $first): Region;
}
