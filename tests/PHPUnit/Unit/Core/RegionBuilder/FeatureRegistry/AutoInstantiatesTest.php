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
 * Acceptance Criterion: FeatureRegistry auto-instantiates missing dependencies during resolution
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class AutoInstantiatesTest extends TestCase
{
    protected function setUp(): void
    {
        BaseTestFeature::reset();
        DependentTestFeature::reset();
    }

    public function testAutoInstantiatesMissingDependency(): void
    {
        $initialCount = BaseTestFeature::$invocationCount;

        $registry = new FeatureRegistry();
        $registry->register(new DependentTestFeature()); // Don't register base

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        // BaseTestFeature should have been auto-instantiated
        $classNames = array_map(fn($f) => $f::class, $resolved);
        $this->assertContains(
            BaseTestFeature::class,
            $classNames,
            'Missing dependency should be auto-instantiated'
        );
    }

    public function testAutoInstantiatedFeatureIsIncludedInResolution(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new DependentTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $classNames = array_map(fn($f) => $f::class, $resolved);

        $this->assertContains(
            BaseTestFeature::class,
            $classNames,
            'Auto-instantiated dependency should be in resolved array'
        );
    }
}
