<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\BaseTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\DependentTestFeature;
use Noem\State\Test\Unit\Core\RegionBuilder\Fixtures\AnotherTestFeature;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Resolution returns array of features in dependency order
 */
#[Group('region-builder')]
#[Group('feature-dependency-resolution')]
class ResolutionOutputTest extends TestCase
{
    public function testResolveReturnsArray(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new BaseTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertIsArray($resolved, 'resolve() should return array');
    }

    public function testArrayContainsFeatureInstances(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new BaseTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(Feature::class, $resolved[0]);
    }

    public function testArrayIsIndexedByIntegers(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new BaseTestFeature());
        $registry->register(new AnotherTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertArrayHasKey(0, $resolved);
        $this->assertArrayHasKey(1, $resolved);
        $this->assertIsInt(array_key_first($resolved));
    }

    public function testArrayOrderReflectsDependencies(): void
    {
        $registry = new FeatureRegistry();
        $registry->register(new DependentTestFeature());

        $resolved = $registry->resolve(new \Noem\State\Middleware\ChainMail());

        $this->assertSame(
            BaseTestFeature::class,
            $resolved[0]::class,
            'First element should be dependency (Base)'
        );
        $this->assertSame(
            DependentTestFeature::class,
            $resolved[1]::class,
            'Second element should be dependent (Dependent)'
        );
    }
}
