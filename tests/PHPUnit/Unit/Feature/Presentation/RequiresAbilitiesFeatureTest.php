<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\RequiresFeature;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationFeature declares AbilitiesFeature dependency via RequiresFeature attribute
 * Intent: Documents discovery mechanism requirement through RequiresFeature attribute declaration
 * Criticality: contract
 */
final class RequiresAbilitiesFeatureTest extends TestCase
{
    public function testDeclaresAbilitiesFeatureDependency(): void
    {
        $reflection = new \ReflectionClass(PresentationFeature::class);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        $this->assertNotEmpty($attributes, 'PresentationFeature must have RequiresFeature attribute');

        // Check if AbilitiesFeature is among the dependencies
        $declaredDependencies = [];
        foreach ($attributes as $attribute) {
            $arguments = $attribute->getArguments();
            $declaredDependencies = array_merge($declaredDependencies, $arguments);
        }

        $this->assertContains(
            AbilitiesFeature::class,
            $declaredDependencies,
            'PresentationFeature must declare AbilitiesFeature dependency'
        );
    }
}
