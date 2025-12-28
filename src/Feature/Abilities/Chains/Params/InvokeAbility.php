<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\Chains\Params;

use Noem\State\Region;

/**
 * Immutable params object for InvokeAbility chain
 *
 * Encapsulates all data needed for ability invocation including
 * target region, ability name, and invocation parameters.
 */
final readonly class InvokeAbility
{
    /**
     * @param Region $region Target region for ability invocation
     * @param string $abilityName Name of ability to invoke
     * @param mixed $parameters Invocation parameters (null for parameterless abilities)
     */
    public function __construct(
        public Region $region,
        public string $abilityName,
        public mixed $parameters = null,
    ) {
    }
}
