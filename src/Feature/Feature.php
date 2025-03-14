<?php

namespace Noem\State\Feature;

use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;

interface Feature
{

    public function __invoke(ChainMail $chainMail): void;
}
