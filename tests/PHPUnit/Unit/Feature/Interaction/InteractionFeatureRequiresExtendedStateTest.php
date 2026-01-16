<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Feature\RequiresFeature;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionFeature declares ExtendedState dependency via RequiresFeature attribute
 * @see specs/features/interaction.yaml - feature-integration-bound-access
 */
class InteractionFeatureRequiresExtendedStateTest extends TestCase
{
    public function testDeclaresExtendedStateDependency(): void
    {
        $reflection = new \ReflectionClass(InteractionFeature::class);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        $this->assertNotEmpty($attributes, 'InteractionFeature must have RequiresFeature attribute');

        $attribute = $attributes[0];
        $arguments = $attribute->getArguments();

        $this->assertContains(
            ExtendedState::class,
            $arguments,
            'InteractionFeature must declare ExtendedState dependency'
        );
    }
}
