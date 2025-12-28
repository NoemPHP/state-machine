<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\Chains\Params;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Region;

/**
 * Parameters for ProcessAbilityResult chain
 *
 * Carries handler result (which may be a Task or actual value) along with
 * metadata needed to create and dispatch the response message.
 */
readonly class ProcessAbilityResult
{
    public function __construct(
        public AbilityMessage $message,
        public mixed $handlerResult,
        public AbilityDefinition $definition,
        public Region $region,
    ) {
    }
}
