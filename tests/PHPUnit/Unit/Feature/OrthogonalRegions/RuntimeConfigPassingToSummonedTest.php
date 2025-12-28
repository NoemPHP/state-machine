<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: summon() can pass custom RuntimeConfig to override parent config
 */
#[Group('orthogonal-regions')]
#[Group('runtime-config')]
class RuntimeConfigPassingToSummonedTest extends TestCase
{
    public function testSummonWithCustomConfigOverridesParent(): void
    {
        $parentIterations = [];
        $childIterations = [];

        $parentCallback = function ($r, $t, $i) use (&$parentIterations) {
            $parentIterations[] = $i;
        };

        $childCallback = function ($r, $t, $i) use (&$childIterations) {
            $childIterations[] = $i;
        };

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', fn(object $t) => 'done');

        $childConfig = new RuntimeConfig(onIteration: $childCallback);

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use ($childBuilder, $childConfig) {
                $runtime = $this->summon($childBuilder, $childConfig);
                $runtime->run();
            })
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $parentConfig = new RuntimeConfig(onIteration: $parentCallback);
        $runtime = new StandardRuntime($region, $parentConfig);
        $runtime->run();

        $this->assertNotEmpty($parentIterations, 'Parent should use parent config');
        $this->assertNotEmpty($childIterations, 'Child should use custom config');
    }

    public function testSummonWithoutConfigInheritsParent(): void
    {
        $sharedIterations = [];

        $sharedCallback = function ($r, $t, $i) use (&$sharedIterations) {
            $sharedIterations[] = $r->currentState();
        };

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', fn(object $t) => 'done');

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                // Summon without config - should inherit parent config
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $parentConfig = new RuntimeConfig(onIteration: $sharedCallback);
        $runtime = new StandardRuntime($region, $parentConfig);
        $runtime->run();

        // Both parent and child should be in the log
        $this->assertContains('parent', $sharedIterations);
        $this->assertContains('child', $sharedIterations);
    }

    public function testDifferentSummonedRegionsHaveDifferentConfigs(): void
    {
        $child1Iterations = 0;
        $child2Iterations = 0;

        $callback1 = function () use (&$child1Iterations) {
            $child1Iterations++;
        };

        $callback2 = function () use (&$child2Iterations) {
            $child2Iterations++;
        };

        $builder1 = (new RegionBuilder())
            ->setStates('c1', 'done1')
            ->markInitial('c1')
            ->markFinal('done1')
            ->onAction('c1', fn(object $t) => 'done1');

        $builder2 = (new RegionBuilder())
            ->setStates('c2', 'done2')
            ->markInitial('c2')
            ->markFinal('done2')
            ->onAction('c2', fn(object $t) => 'done2');

        $config1 = new RuntimeConfig(onIteration: $callback1);
        $config2 = new RuntimeConfig(onIteration: $callback2);

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($builder1, $builder2, $config1, $config2) {
                $this->summon($builder1, $config1)->run();
                $this->summon($builder2, $config2)->run();
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run(steps: 1);

        $this->assertGreaterThan(0, $child1Iterations);
        $this->assertGreaterThan(0, $child2Iterations);
    }

    public function testSummonConfigCanHaveDifferentMaxIterations(): void
    {
        $childHitLimit = false;

        $infiniteBuilder = (new RegionBuilder())
            ->setStates('infinite')
            ->markInitial('infinite')
            ->onAction('infinite', fn(object $t) => 'infinite');

        $childConfig = new RuntimeConfig(maxIterations: 3);

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($infiniteBuilder, $childConfig, &$childHitLimit) {
                try {
                    $this->summon($infiniteBuilder, $childConfig)->run();
                } catch (\Noem\State\Exception\MaxIterationsException $e) {
                    $childHitLimit = true;
                }
            })
            ->build();

        $parentConfig = new RuntimeConfig(maxIterations: 1000);
        $runtime = new StandardRuntime($region, $parentConfig);
        $runtime->run(steps: 1);

        $this->assertTrue($childHitLimit, 'Child should hit its own maxIterations limit');
    }

    public function testSummonConfigCanOverrideParentTriggerFactory(): void
    {
        $parentTriggersUsed = false;
        $childTriggersUsed = false;

        $parentFactory = function ($i, $r) use (&$parentTriggersUsed) {
            $parentTriggersUsed = true;
            return (object)['type' => 'parent'];
        };

        $childFactory = function ($i, $r) use (&$childTriggersUsed) {
            $childTriggersUsed = true;
            return (object)['type' => 'child'];
        };

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', fn(object $t) => 'done');

        $childConfig = new RuntimeConfig(triggerFactory: $childFactory);

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use ($childBuilder, $childConfig) {
                $this->summon($childBuilder, $childConfig)->run();
            })
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $parentConfig = new RuntimeConfig(triggerFactory: $parentFactory);
        $runtime = new StandardRuntime($region, $parentConfig);
        $runtime->run();

        $this->assertTrue($parentTriggersUsed, 'Parent should use parent factory');
        $this->assertTrue($childTriggersUsed, 'Child should use custom factory');
    }
}
