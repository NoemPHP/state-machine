<?php

declare(strict_types=1);

namespace Noem\State;

/**
 * Configuration object for Runtime execution
 *
 * Value object containing runtime execution parameters including iteration limits,
 * trigger generation, and lifecycle callbacks.
 */
readonly class RuntimeConfig
{
    /**
     * @param int $maxIterations Maximum number of iterations before throwing MaxIterationsException (0 or -1 for unlimited)
     * @param \Closure(int, Region): object|null $triggerFactory Factory function to create custom triggers
     * @param \Closure(Region, object, int): void|null $onIteration Callback fired on each iteration
     * @param \Closure(): void|null $onComplete Callback fired when region reaches final state
     */
    public function __construct(
        public int $maxIterations = 10000,
        public ?\Closure $triggerFactory = null,
        public ?\Closure $onIteration = null,
        public ?\Closure $onComplete = null,
    ) {
    }
}
