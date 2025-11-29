<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\FeatureRegistry;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\BaseTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\DependentTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureA;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureB;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureC;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Topological sort places dependencies before dependents
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class DependenciesFirstOrderTest extends TestCase
{
    public function testDependenciesAppearBeforeDependents(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new DependentTestFeature());  // Register in reverse order
        $registry->register(new BaseTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        // Find positions
        $posA = null;
        $posB = null;
        foreach ($resolved as $index => $feature) {
            if ($feature::class === BaseTestFeature::class) {
                $posA = $index;
            }
            if ($feature::class === DependentTestFeature::class) {
                $posB = $index;
            }
        }

        $this->assertNotNull($posA);
        $this->assertNotNull($posB);
        $this->assertLessThan(
            $posB,
            $posA,
            'Dependency Base should appear before dependent Dependent in sorted array'
        );
    }

    public function testTransitiveDependenciesAppearInOrder(): void
    {
        // Chain: A → B → C
        $registry = new FeatureRegistry();
        $registry->register(new TransitiveTestFeatureC());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $positions = [];
        foreach ($resolved as $index => $feature) {
            $positions[$feature::class] = $index;
        }

        $this->assertLessThan($positions[TransitiveTestFeatureB::class], $positions[TransitiveTestFeatureA::class]);
        $this->assertLessThan($positions[TransitiveTestFeatureC::class], $positions[TransitiveTestFeatureB::class]);
        $this->assertLessThan($positions[TransitiveTestFeatureC::class], $positions[TransitiveTestFeatureA::class]);
    }
}
