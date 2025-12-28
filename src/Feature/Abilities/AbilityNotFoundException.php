<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities;

/**
 * Exception thrown when attempting to invoke a non-existent or hidden ability
 */
class AbilityNotFoundException extends \RuntimeException
{
    public function __construct(string $abilityName)
    {
        parent::__construct(
            sprintf('Ability "%s" not found or not available', $abilityName)
        );
    }
}
