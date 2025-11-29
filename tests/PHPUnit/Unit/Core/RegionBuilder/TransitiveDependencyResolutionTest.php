<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureA;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureB;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureC;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Features with multi-level dependencies resolve recursively
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class TransitiveDependencyResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        TransitiveTestFeatureA::reset();
        TransitiveTestFeatureB::reset();
        TransitiveTestFeatureC::reset();
    }

    public function testTransitiveDependenciesAreResolved(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new TransitiveTestFeatureC()); // Only enable C
        $builder->setStates('idle')->build();

        $this->assertTrue(TransitiveTestFeatureA::$invoked, 'Transitive dependency A should be resolved');
        $this->assertTrue(TransitiveTestFeatureB::$invoked, 'Direct dependency B should be resolved');
        $this->assertSame(['A', 'B', 'C'], TransitiveTestFeatureC::$invocationOrder, 'All dependencies should invoke in order');
    }

    public function testDeepTransitiveDependenciesAreResolved(): void
    {
        // Use 3-level chain: A → B → C
        $builder = new RegionBuilder();
        $builder->enableFeatures(new TransitiveTestFeatureC()); // Only enable the leaf
        $builder->setStates('idle')->build();

        $this->assertTrue(TransitiveTestFeatureA::$invoked, 'Deep transitive dependency should be resolved');
        $this->assertTrue(TransitiveTestFeatureB::$invoked);
        $this->assertSame(['A', 'B', 'C'], TransitiveTestFeatureC::$invocationOrder);
    }
}
