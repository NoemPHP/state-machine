<?php

declare(strict_types=1);

namespace Noem\State;

/**
 * Standard runtime implementation
 *
 * Creates anonymous stdClass triggers with a 'result' property.
 * Inherits all behavior from Runtime base class.
 */
class StandardRuntime extends Runtime
{
    /**
     * Create default trigger as stdClass with result property
     *
     * @param int $iteration Current iteration number
     * @return object Anonymous stdClass trigger
     */
    protected function createDefaultTrigger(int $iteration): object
    {
        return (object)['result' => null];
    }
}
