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
 * Acceptance Criterion: FeatureRegistry performs topological sort on dependency graph
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class TopologicalSortTest extends TestCase
{
    public function testResolvePerformsTopologicalSort(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new DependentTestFeature());
        $registry->register(new BaseTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        // Topological sort should order dependencies before dependents
        $this->assertIsArray($resolved);
        $this->assertCount(2, $resolved);

        // First feature should be Base (no dependencies)
        $this->assertInstanceOf(BaseTestFeature::class, $resolved[0]);
    }

    public function testSortHandlesComplexDependencyGraph(): void
    {
        // MultiDependencyTestFeature depends on both Base and Another
        $registry = new FeatureRegistry();
        $registry->register(new MultiDependencyTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertCount(3, $resolved);

        // MultiDependency should be last
        $this->assertInstanceOf(MultiDependencyTestFeature::class, $resolved[2]);

        // Base and Another should both come before MultiDependency
        $positions = [];
        foreach ($resolved as $index => $feature) {
            $positions[$feature::class] = $index;
        }

        $this->assertLessThan($positions[MultiDependencyTestFeature::class], $positions[BaseTestFeature::class]);
    }
}
