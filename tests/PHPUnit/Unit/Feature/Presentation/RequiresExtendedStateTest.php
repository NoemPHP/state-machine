<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\RequiresFeature;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationFeature declares ExtendedState dependency via RequiresFeature attribute
 * Intent: Documents context storage requirement through RequiresFeature attribute declaration
 * Criticality: contract
 */
final class RequiresExtendedStateTest extends TestCase
{
    public function testDeclaresExtendedStateDependency(): void
    {
        $reflection = new \ReflectionClass(PresentationFeature::class);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        $this->assertNotEmpty($attributes, 'PresentationFeature must have RequiresFeature attribute');

        // Check if ExtendedState is among the dependencies
        $declaredDependencies = [];
        foreach ($attributes as $attribute) {
            $arguments = $attribute->getArguments();
            $declaredDependencies = array_merge($declaredDependencies, $arguments);
        }

        $this->assertContains(
            ExtendedState::class,
            $declaredDependencies,
            'PresentationFeature must declare ExtendedState dependency'
        );
    }
}
