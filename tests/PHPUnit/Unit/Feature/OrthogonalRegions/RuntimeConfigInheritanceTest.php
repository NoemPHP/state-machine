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
 * Acceptance Criterion: Nested orthogonal regions inherit RuntimeConfig through hierarchy
 */
#[Group('orthogonal-regions')]
#[Group('runtime-config')]
class RuntimeConfigInheritanceTest extends TestCase
{
    public function testGrandchildInheritsParentConfig(): void
    {
        $iterationLog = [];

        $onIteration = function ($r, $t, $i) use (&$iterationLog) {
            $iterationLog[] = $r->currentState();
        };

        $grandchild = (new RegionBuilder())
            ->setStates('grandchild', 'done_gc')
            ->markInitial('grandchild')
            ->markFinal('done_gc')
            ->onAction('grandchild', fn(object $t) => 'done_gc');

        $child = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('child', 'done_c')
            ->markInitial('child')
            ->markFinal('done_c')
            ->onEnter('child', function (object $t) use ($grandchild) {
                $this->summon($grandchild)->run();
            })
            ->onAction('child', fn(object $t) => 'done_c');

        $parent = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent', 'done_p')
            ->markInitial('parent')
            ->markFinal('done_p')
            ->onEnter('parent', function (object $t) use ($child) {
                $this->summon($child)->run();
            })
            ->onAction('parent', fn(object $t) => 'done_p')
            ->build();

        $config = new RuntimeConfig(onIteration: $onIteration);
        $runtime = new StandardRuntime($parent, $config);
        $runtime->run();

        // All levels should appear in the log
        $this->assertContains('parent', $iterationLog);
        $this->assertContains('child', $iterationLog);
        $this->assertContains('grandchild', $iterationLog);
    }

    public function testMidLevelConfigOverrideAffectsDescendants(): void
    {
        $parentIterations = [];
        $childIterations = [];
        $grandchildIterations = [];

        $parentCallback = function ($r, $t, $i) use (&$parentIterations) {
            $parentIterations[] = $i;
        };

        $childCallback = function ($r, $t, $i) use (&$childIterations, &$grandchildIterations) {
            $state = $r->currentState();
            if ($state === 'child' || $state === 'done_c') {
                $childIterations[] = $i;
            } elseif ($state === 'grandchild' || $state === 'done_gc') {
                $grandchildIterations[] = $i;
            }
        };

        $grandchild = (new RegionBuilder())
            ->setStates('grandchild', 'done_gc')
            ->markInitial('grandchild')
            ->markFinal('done_gc')
            ->onAction('grandchild', fn(object $t) => 'done_gc');

        $childConfig = new RuntimeConfig(onIteration: $childCallback);

        $child = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('child', 'done_c')
            ->markInitial('child')
            ->markFinal('done_c')
            ->onEnter('child', function (object $t) use ($grandchild) {
                // Grandchild inherits child's config, not parent's
                $this->summon($grandchild)->run();
            })
            ->onAction('child', fn(object $t) => 'done_c');

        $parent = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent', 'done_p')
            ->markInitial('parent')
            ->markFinal('done_p')
            ->onEnter('parent', function (object $t) use ($child, $childConfig) {
                // Override config at child level
                $this->summon($child, $childConfig)->run();
            })
            ->onAction('parent', fn(object $t) => 'done_p')
            ->build();

        $parentConfig = new RuntimeConfig(onIteration: $parentCallback);
        $runtime = new StandardRuntime($parent, $parentConfig);
        $runtime->run();

        $this->assertNotEmpty($parentIterations, 'Parent uses parent callback');
        $this->assertNotEmpty($childIterations, 'Child uses child callback');
        $this->assertNotEmpty($grandchildIterations, 'Grandchild inherits child callback');
    }

    public function testDeepNestingPreservesConfigInheritance(): void
    {
        $allIterations = [];

        $sharedCallback = function ($r, $t, $i) use (&$allIterations) {
            $allIterations[] = $r->currentState();
        };

        $level4 = (new RegionBuilder())
            ->setStates('l4', 'd4')
            ->markInitial('l4')
            ->markFinal('d4')
            ->onAction('l4', fn(object $t) => 'd4');

        $level3 = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('l3', 'd3')
            ->markInitial('l3')
            ->markFinal('d3')
            ->onEnter('l3', function (object $t) use ($level4) {
                $this->summon($level4)->run();
            })
            ->onAction('l3', fn(object $t) => 'd3');

        $level2 = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('l2', 'd2')
            ->markInitial('l2')
            ->markFinal('d2')
            ->onEnter('l2', function (object $t) use ($level3) {
                $this->summon($level3)->run();
            })
            ->onAction('l2', fn(object $t) => 'd2');

        $level1 = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('l1', 'd1')
            ->markInitial('l1')
            ->markFinal('d1')
            ->onEnter('l1', function (object $t) use ($level2) {
                $this->summon($level2)->run();
            })
            ->onAction('l1', fn(object $t) => 'd1')
            ->build();

        $config = new RuntimeConfig(onIteration: $sharedCallback);
        $runtime = new StandardRuntime($level1, $config);
        $runtime->run();

        // All 4 levels should be in the log
        $this->assertContains('l1', $allIterations);
        $this->assertContains('l2', $allIterations);
        $this->assertContains('l3', $allIterations);
        $this->assertContains('l4', $allIterations);
    }

    public function testStaticAndSummonedRegionsBothInheritConfig(): void
    {
        $staticIterations = [];
        $summonedIterations = [];

        $callback = function ($r, $t, $i) use (&$staticIterations, &$summonedIterations) {
            $state = $r->currentState();
            if ($state === 'static' || $state === 'done_s') {
                $staticIterations[] = $i;
            } elseif ($state === 'summoned' || $state === 'done_sm') {
                $summonedIterations[] = $i;
            }
        };

        $staticChild = (new RegionBuilder())
            ->setStates('static', 'done_s')
            ->markInitial('static')
            ->markFinal('done_s')
            ->onAction('static', fn(object $t) => 'done_s');

        $summonedChild = (new RegionBuilder())
            ->setStates('summoned', 'done_sm')
            ->markInitial('summoned')
            ->markFinal('done_sm')
            ->onAction('summoned', fn(object $t) => 'done_sm');

        $parent = (new OrthogonalRegions(new RegionBuilder(), [$staticChild]))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use ($summonedChild) {
                $this->summon($summonedChild)->run();
            })
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $config = new RuntimeConfig(onIteration: $callback);
        $runtime = new StandardRuntime($parent, $config);
        $runtime->run();

        $this->assertNotEmpty($staticIterations, 'Static child should inherit config');
        $this->assertNotEmpty($summonedIterations, 'Summoned child should inherit config');
    }

    public function testMaxIterationsInheritedThroughHierarchy(): void
    {
        $grandchildHitLimit = false;

        $infiniteGrandchild = (new RegionBuilder())
            ->setStates('infinite')
            ->markInitial('infinite')
            ->onAction('infinite', fn(object $t) => 'infinite');

        $child = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use ($infiniteGrandchild, &$grandchildHitLimit) {
                try {
                    $this->summon($infiniteGrandchild)->run();
                } catch (\Noem\State\Exception\MaxIterationsException $e) {
                    $grandchildHitLimit = true;
                }
            });

        $parent = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($child) {
                $this->summon($child)->run(steps: 1);
            })
            ->build();

        $config = new RuntimeConfig(maxIterations: 5);
        $runtime = new StandardRuntime($parent, $config);
        $runtime->run(steps: 1);

        $this->assertTrue($grandchildHitLimit, 'Grandchild should inherit maxIterations from root');
    }
}
