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
 * Acceptance Criterion: FeatureRegistry builds dependency graph with adjacency list
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class BuildsDependencyGraphTest extends TestCase
{
    public function testGraphRepresentsDependencyRelationships(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new BaseTestFeature());
        $registry->register(new DependentTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        // If graph is correctly built, topological sort will succeed
        $this->assertIsArray($resolved, 'Resolve should return array indicating successful graph construction');
        $this->assertNotEmpty($resolved, 'Graph should contain features');
    }

    public function testGraphHandlesMultipleDependencies(): void
    {
        // MultiDependency depends on both Base and Another
        $registry = new FeatureRegistry();
        $registry->register(new MultiDependencyTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertCount(
            3,
            $resolved,
            'Graph should resolve all unique nodes'
        );
    }
}
