<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\RequiresFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\BaseTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\DependentTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\MultiDependencyTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\AnotherTestFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Features can declare dependencies using multiple RequiresFeature attributes
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class FeatureDependencyDeclarationTest extends TestCase
{
    public function testFeatureCanDeclareSingleDependency(): void
    {
        $reflection = new \ReflectionClass(DependentTestFeature::class);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        $this->assertCount(1, $attributes, 'Feature should have one RequiresFeature attribute');

        $requiresFeature = $attributes[0]->newInstance();
        $this->assertSame(
            BaseTestFeature::class,
            $requiresFeature->featureFQCN,
            'RequiresFeature should reference the dependency class'
        );
    }

    public function testFeatureCanDeclareMultipleDependencies(): void
    {
        $reflection = new \ReflectionClass(MultiDependencyTestFeature::class);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        $this->assertCount(2, $attributes, 'Feature should have two RequiresFeature attributes');

        $dependencies = array_map(
            fn($attr) => $attr->newInstance()->featureFQCN,
            $attributes
        );

        $this->assertContains(BaseTestFeature::class, $dependencies);
        $this->assertContains(AnotherTestFeature::class, $dependencies);
    }
}
