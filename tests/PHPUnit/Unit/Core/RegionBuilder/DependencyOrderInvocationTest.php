<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\BaseTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\DependentTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureA;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureB;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\TransitiveTestFeatureC;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Features are invoked in dependency order during build
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class DependencyOrderInvocationTest extends TestCase
{
    protected function setUp(): void
    {
        BaseTestFeature::reset();
        DependentTestFeature::reset();
        TransitiveTestFeatureA::reset();
        TransitiveTestFeatureB::reset();
        TransitiveTestFeatureC::reset();
    }

    public function testDependencyInvokedBeforeDependent(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new DependentTestFeature(), new BaseTestFeature()); // Wrong order intentionally
        $builder->setStates('idle')->build();

        $this->assertSame(
            ['base', 'dependent'],
            DependentTestFeature::$invocationOrder,
            'Dependency should be invoked before dependent regardless of enableFeatures order'
        );
    }

    public function testMultipleDependenciesInvokedInCorrectOrder(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new TransitiveTestFeatureC()); // Only enable C, let deps auto-resolve
        $builder->setStates('idle')->build();

        $this->assertSame(
            ['A', 'B', 'C'],
            TransitiveTestFeatureC::$invocationOrder,
            'Features should invoke in dependency order: A → B → C'
        );
    }
}
