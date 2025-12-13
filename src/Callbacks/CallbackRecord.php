<?php

declare(strict_types=1);

namespace Noem\State\Callbacks;

use Noem\State\Region;

/**
 * Immutable value object for storing callback metadata.
 *
 * Encapsulates all metadata needed to identify and invoke a callback, providing
 * complete context for registry queries. Properties are readonly and immutable to
 * prevent accidental modification after registration.
 */
class CallbackRecord
{
    public function __construct(
        public readonly Region $region,
        public readonly CallbackType $type,
        public readonly string $event,
        public readonly string $state,
        public readonly \Closure $callback,
        public readonly mixed $metadata = null,
    ) {
        if (!in_array($event, ['action', 'enter', 'exit'], true)) {
            throw new \InvalidArgumentException(
                "Event must be 'action', 'enter', or 'exit', got '{$event}'"
            );
        }
    }
}
