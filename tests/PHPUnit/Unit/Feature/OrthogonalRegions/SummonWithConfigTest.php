<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use Noem\State\RuntimeConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: summon() accepts optional RuntimeConfig parameter
 */
#[Group('orthogonal-regions')]
#[Group('summon')]
class SummonWithConfigTest extends TestCase
{
    public function testSummonAcceptsRuntimeConfig(): void
    {
        $configAccepted = false;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $childConfig = new RuntimeConfig(maxIterations: 500);

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, $childConfig, &$configAccepted) {
                try {
                    $this->summon($childBuilder, $childConfig);
                    $configAccepted = true;
                } catch (\Throwable $e) {
                    $configAccepted = false;
                }
            })
            ->build();

        $parentRegion->init();

        $this->assertTrue($configAccepted, 'summon() should accept RuntimeConfig');
    }

    public function testSummonedRuntimeUsesProvidedConfig(): void
    {
        $actualConfig = null;

        $onIterationCalled = false;
        $callback = function () use (&$onIterationCalled) {
            $onIterationCalled = true;
        };

        $childConfig = new RuntimeConfig(
            maxIterations: 750,
            onIteration: $callback
        );

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', fn(object $t) => 'done');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, $childConfig, &$actualConfig) {
                $runtime = $this->summon($childBuilder, $childConfig);
                $actualConfig = $runtime->getConfig();
                $runtime->run();
            })
            ->build();

        $parentRegion->init();

        $this->assertSame($childConfig, $actualConfig);
        $this->assertTrue($onIterationCalled, 'Config callbacks should be executed');
    }

    public function testSummonWithoutConfigUsesDefaultConfig(): void
    {
        $actualConfig = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$actualConfig) {
                $runtime = $this->summon($childBuilder);
                $actualConfig = $runtime->getConfig();
            })
            ->build();

        $parentRegion->init();

        $this->assertInstanceOf(RuntimeConfig::class, $actualConfig);
        $this->assertEquals(10000, $actualConfig->maxIterations, 'Should use default maxIterations');
    }

    public function testDifferentChildrenCanHaveDifferentConfigs(): void
    {
        $config1Iterations = null;
        $config2Iterations = null;

        $childBuilder1 = (new RegionBuilder())
            ->setStates('child1', 'done1')
            ->markInitial('child1')
            ->markFinal('done1')
            ->onAction('child1', fn(object $t) => 'done1');

        $childBuilder2 = (new RegionBuilder())
            ->setStates('child2', 'done2')
            ->markInitial('child2')
            ->markFinal('done2')
            ->onAction('child2', fn(object $t) => 'done2');

        $config1 = new RuntimeConfig(maxIterations: 100);
        $config2 = new RuntimeConfig(maxIterations: 200);

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use (
                $childBuilder1,
                $childBuilder2,
                $config1,
                $config2,
                &$config1Iterations,
                &$config2Iterations
            ) {
                $runtime1 = $this->summon($childBuilder1, $config1);
                $config1Iterations = $runtime1->getConfig()->maxIterations;

                $runtime2 = $this->summon($childBuilder2, $config2);
                $config2Iterations = $runtime2->getConfig()->maxIterations;
            })
            ->build();

        $parentRegion->init();

        $this->assertEquals(100, $config1Iterations);
        $this->assertEquals(200, $config2Iterations);
    }

    public function testSummonConfigOverridesParentConfig(): void
    {
        $parentConfig = new RuntimeConfig(maxIterations: 1000);
        $childConfig = new RuntimeConfig(maxIterations: 500);

        $actualChildMaxIterations = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $parentRegion = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, $childConfig, &$actualChildMaxIterations) {
                $runtime = $this->summon($childBuilder, $childConfig);
                $actualChildMaxIterations = $runtime->getConfig()->maxIterations;
            })
            ->build();

        $parentRuntime = new \Noem\State\StandardRuntime($parentRegion, $parentConfig);
        $parentRuntime->run(steps: 1);

        $this->assertEquals(500, $actualChildMaxIterations, 'Child config should override parent config');
    }
}
