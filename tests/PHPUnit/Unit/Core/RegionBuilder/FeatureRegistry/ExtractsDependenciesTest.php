<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\FeatureRegistry;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\BaseTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\DependentTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\MultiDependencyTestFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry extracts dependencies from RequiresFeature attributes
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class ExtractsDependenciesTest extends TestCase
{
    public function testExtractsSingleDependency(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new DependentTestFeature());

        // Resolve should auto-discover and register the dependency
        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertCount(
            2,
            $resolved,
            'Registry should contain both the feature and its dependency'
        );
    }

    public function testExtractsMultipleDependencies(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new MultiDependencyTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertCount(
            3,
            $resolved,
            'Registry should contain feature and both dependencies'
        );
    }
}
