<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\BaseTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\DependentTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\MultiDependencyTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\AnotherTestFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Missing feature dependencies are auto-instantiated during build
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class AutoInstantiateDependenciesTest extends TestCase
{
    protected function setUp(): void
    {
        BaseTestFeature::reset();
        DependentTestFeature::reset();
        AnotherTestFeature::reset();
    }

    public function testMissingDependencyIsAutoInstantiated(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new DependentTestFeature()); // Only enable dependent, not base
        $builder->setStates('idle')->build();

        $this->assertGreaterThan(
            0,
            BaseTestFeature::$invocationCount,
            'Missing dependency should be auto-instantiated and invoked'
        );
    }

    public function testMultipleMissingDependenciesAreAutoInstantiated(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new MultiDependencyTestFeature());
        $builder->setStates('idle')->build();

        $this->assertGreaterThan(0, BaseTestFeature::$invocationCount, 'First dependency should be auto-instantiated');
        $this->assertGreaterThan(0, AnotherTestFeature::$invocationCount, 'Second dependency should be auto-instantiated');
    }
}
