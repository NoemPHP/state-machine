<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\OrthogonalRegions;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use Noem\State\RuntimeConfig;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Complete workflow from static regions to dynamic summon with context
 */
#[Group('orthogonal-regions')]
#[Group('integration')]
class OrthogonalRegionsFullWorkflowTest extends TestCase
{
    public function testCompleteStaticAndDynamicWorkflow(): void
    {
        $executionLog = [];

        // Static child that modifies context
        $staticChild = (new RegionBuilder())
            ->setStates('static', 'static_done')
            ->markInitial('static')
            ->markFinal('static_done')
            ->onEnter('static', function (object $t) use (&$executionLog) {
                $executionLog[] = 'static_enter';
                $this->set('counter', 10);
            })
            ->onAction('static', function (object $t) use (&$executionLog) {
                $executionLog[] = 'static_action';
                return 'static_done';
            });

        // Dynamic child builder
        $dynamicChildBuilder = (new RegionBuilder())
            ->setStates('dynamic', 'dynamic_done')
            ->markInitial('dynamic')
            ->markFinal('dynamic_done')
            ->onEnter('dynamic', function (object $t) use (&$executionLog) {
                $executionLog[] = 'dynamic_enter';
                $counter = $this->get('counter');
                $this->set('counter', $counter + 5);
            })
            ->onAction('dynamic', function (object $t) use (&$executionLog) {
                $executionLog[] = 'dynamic_action';
                return 'dynamic_done';
            });

        // Parent region with both static and summoned children
        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$staticChild]
        ))
            ->setStates('parent', 'spawning', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use (&$executionLog) {
                $executionLog[] = 'parent_enter';
            })
            ->onAction('parent', function (object $t) use (&$executionLog) {
                $executionLog[] = 'parent_to_spawning';
                return 'spawning';
            })
            ->onEnter('spawning', function (object $t) use ($dynamicChildBuilder, &$executionLog) {
                $executionLog[] = 'spawning_enter';
                $runtime = $this->summon($dynamicChildBuilder);
                $runtime->run();
            })
            ->onAction('spawning', function (object $t) use (&$executionLog) {
                $executionLog[] = 'spawning_to_done';
                return 'done';
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $expectedFlow = [
            'parent_enter',
            'static_enter',
            'static_action',
            'parent_to_spawning',
            'spawning_enter',
            'dynamic_enter',
            'dynamic_action',
            'spawning_to_done',
        ];

        $this->assertEquals($expectedFlow, $executionLog);
        $this->assertEquals(15, $region->context->get('counter')); // 10 + 5
        $this->assertTrue($runtime->isComplete());
    }

    public function testNestedOrthogonalRegionsWithFullContextSharing(): void
    {
        $contextLog = [];

        // Grandchild
        $grandchild = (new RegionBuilder())
            ->setStates('gc', 'gc_done')
            ->markInitial('gc')
            ->markFinal('gc_done')
            ->onEnter('gc', function (object $t) use (&$contextLog) {
                $value = $this->get('shared_value');
                $contextLog[] = "grandchild_read: {$value}";
                $this->set('shared_value', $value . '_gc');
            })
            ->onAction('gc', fn(object $t) => 'gc_done');

        // Child with static grandchild
        $child = (new OrthogonalRegions(
            new RegionBuilder(),
            [$grandchild]
        ))
            ->setStates('child', 'child_done')
            ->markInitial('child')
            ->markFinal('child_done')
            ->onEnter('child', function (object $t) use (&$contextLog) {
                $value = $this->get('shared_value');
                $contextLog[] = "child_read: {$value}";
                $this->set('shared_value', $value . '_child');
            })
            ->onAction('child', fn(object $t) => 'child_done');

        // Parent
        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use (&$contextLog) {
                $this->set('shared_value', 'parent');
                $contextLog[] = 'parent_set: parent';
            })
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals([
            'parent_set: parent',
            'child_read: parent',
            'grandchild_read: parent_child',
        ], $contextLog);

        $this->assertEquals('parent_child_gc', $region->context->get('shared_value'));
        $this->assertTrue($runtime->isComplete());
    }

    public function testCooperativeExecutionWithOrthogonalRegions(): void
    {
        $executionOrder = [];

        $child1Builder = (new RegionBuilder())
            ->setStates('c1', 'c1_done')
            ->markInitial('c1')
            ->markFinal('c1_done')
            ->onAction('c1', function (object $t) use (&$executionOrder) {
                $executionOrder[] = 'child1';
                return 'c1_done';
            });

        $child2Builder = (new RegionBuilder())
            ->setStates('c2', 'c2_done')
            ->markInitial('c2')
            ->markFinal('c2_done')
            ->onAction('c2', function (object $t) use (&$executionOrder) {
                $executionOrder[] = 'child2';
                return 'c2_done';
            });

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($child1Builder, $child2Builder, &$executionOrder) {
                $runtime1 = $this->summon($child1Builder);
                $runtime2 = $this->summon($child2Builder);

                // Execute cooperatively
                while (!$runtime1->isComplete() || !$runtime2->isComplete()) {
                    if (!$runtime1->isComplete()) {
                        $runtime1->run(steps: 1);
                    }
                    if (!$runtime2->isComplete()) {
                        $runtime2->run(steps: 1);
                    }
                }
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run(steps: 1);

        $this->assertEquals(['child1', 'child2'], $executionOrder);
    }

    public function testDynamicRegionCreationBasedOnContext(): void
    {
        $createdRegions = [];

        $createChildBuilder = function ($type) {
            return (new RegionBuilder())
                ->setStates($type)
                ->markInitial($type)
                ->onEnter($type, function (object $t) use ($type) {
                    $this->set("{$type}_visited", true);
                });
        };

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use ($createChildBuilder, &$createdRegions) {
                $this->set('config', ['alpha', 'beta', 'gamma']);
            })
            ->onAction('parent', function (object $t) use ($createChildBuilder, &$createdRegions) {
                $config = $this->get('config');
                foreach ($config as $type) {
                    $builder = $createChildBuilder($type);
                    $this->summon($builder)->run();
                    $createdRegions[] = $type;
                }
                return 'done';
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertEquals(['alpha', 'beta', 'gamma'], $createdRegions);
        $this->assertTrue($region->context->get('alpha_visited'));
        $this->assertTrue($region->context->get('beta_visited'));
        $this->assertTrue($region->context->get('gamma_visited'));
    }

    public function testRuntimeConfigFlowThroughOrthogonalHierarchy(): void
    {
        $iterationCounts = [];

        $onIteration = function ($r, $t, $i) use (&$iterationCounts) {
            $state = $r->currentState();
            if (!isset($iterationCounts[$state])) {
                $iterationCounts[$state] = 0;
            }
            $iterationCounts[$state]++;
        };

        $grandchild = (new RegionBuilder())
            ->setStates('gc', 'gc_done')
            ->markInitial('gc')
            ->markFinal('gc_done')
            ->onAction('gc', fn(object $t) => 'gc_done');

        $child = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('child', 'child_done')
            ->markInitial('child')
            ->markFinal('child_done')
            ->onEnter('child', function (object $t) use ($grandchild) {
                $this->summon($grandchild)->run();
            })
            ->onAction('child', fn(object $t) => 'child_done');

        $parent = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use ($child) {
                $this->summon($child)->run();
            })
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $config = new RuntimeConfig(onIteration: $onIteration);
        $runtime = new StandardRuntime($parent, $config);
        $runtime->run();

        // All levels should have received iteration callbacks
        $this->assertArrayHasKey('parent', $iterationCounts);
        $this->assertArrayHasKey('child', $iterationCounts);
        $this->assertArrayHasKey('gc', $iterationCounts);
    }
}
