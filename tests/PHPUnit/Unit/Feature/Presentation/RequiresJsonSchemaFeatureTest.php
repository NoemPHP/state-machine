<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation;

use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\RequiresFeature;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationFeature declares JsonSchemaFeature dependency via RequiresFeature attribute
 * Intent: Documents schema validation requirement through RequiresFeature attribute declaration
 * Criticality: contract
 */
final class RequiresJsonSchemaFeatureTest extends TestCase
{
    public function testDeclaresJsonSchemaFeatureDependency(): void
    {
        $reflection = new \ReflectionClass(PresentationFeature::class);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        $this->assertNotEmpty($attributes, 'PresentationFeature must have RequiresFeature attribute');

        // Check if JsonSchemaFeature is among the dependencies
        $declaredDependencies = [];
        foreach ($attributes as $attribute) {
            $arguments = $attribute->getArguments();
            $declaredDependencies = array_merge($declaredDependencies, $arguments);
        }

        $this->assertContains(
            JsonSchemaFeature::class,
            $declaredDependencies,
            'PresentationFeature must declare JsonSchemaFeature dependency'
        );
    }
}
