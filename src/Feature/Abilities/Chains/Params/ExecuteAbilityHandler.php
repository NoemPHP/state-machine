<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\Chains\Params;

use Noem\State\Region;

/**
 * Parameters for ExecuteAbilityHandler chain
 *
 * Encapsulates handler callable, parameters, and region context
 * for ability execution. Allows AsyncFeature to intercept and
 * enqueue generator handlers.
 */
final readonly class ExecuteAbilityHandler
{
    /**
     * @param callable $handler The ability handler to execute
     * @param mixed $parameters Parameters to pass to handler
     * @param Region $region Region context for execution
     */
    public function __construct(
        public mixed $handler,
        public mixed $parameters,
        public Region $region,
    ) {
    }
}
