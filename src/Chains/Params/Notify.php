<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Region;

/**
 * Context for listener resolution
 *
 * Carries both Region and event.
 * Region identifies event source for listeners.
 */
class Notify
{
    public function __construct(
        public readonly Region $region,
        public readonly object $event,
    ) {
    }
}
