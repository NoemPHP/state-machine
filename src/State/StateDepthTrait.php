<?php

declare(strict_types=1);

namespace Noem\State\State;

use Noem\State\NestedStateInterface;
use Noem\State\StateInterface;

trait StateDepthTrait
{
    private function getDepth(StateInterface $state): int
    {
        if (!$state instanceof NestedStateInterface) {
            return 0;
        }
        $i = 0;
        while ($state = $state->parent()) {
            $i++;
        }

        return $i;
    }
}
