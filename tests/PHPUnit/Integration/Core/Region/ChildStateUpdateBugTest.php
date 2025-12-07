<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Region;

use Noem\State\Connection;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * This test exposes a bug: when a parent region triggers an action,
 * child regions receive the action (callbacks fire) but their state
 * is never updated and transitions never execute.
 */
#[Group('region')]
#[Group('bug')]
class ChildStateUpdateBugTest extends TestCase
{
    public function testChildRegionStateDoesNotUpdateWhenTriggeredThroughParent(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        // Track which state's action fired
        $stateLog = [];

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'regions' => [
                        [
                            'states' => [
                                [
                                    'name' => 'child1',
                                    'transitions' => [
                                        ['target' => 'child2', 'guard' => fn($t) => true],
                                    ],
                                    'action' => [
                                        ['run' => function ($t) use (&$stateLog) {
                                            $stateLog[] = 'child1';
                                            return 'child1';
                                        }],
                                    ],
                                ],
                                [
                                    'name' => 'child2',
                                    'action' => [
                                        ['run' => function ($t) use (&$stateLog) {
                                            $stateLog[] = 'child2';
                                            return 'child2';
                                        }],
                                    ],
                                ],
                            ],
                            'initial' => 'child1',
                        ],
                    ],
                ],
            ],
            'initial' => 'parent',
        ];

        $parent = $builder->build(['loader' => ['array' => $config]]);

        // First trigger: child1's action should fire
        $parent->trigger(new stdClass());
        $this->assertEquals(['child1'], $stateLog, 'First trigger: child1 action fires');

        // Second trigger: child should transition to child2, so child2's action should fire
        $parent->trigger(new stdClass());

        // BUG: child1's action fires again because child never transitioned!
        $this->assertEquals(
            ['child1', 'child2'],
            $stateLog,
            'BUG: Child should have transitioned to child2, but child1 fired again!'
        );
    }

    public function testChildRegionCanBeQueriedForCurrentState(): void
    {
        $this->markTestSkipped('Cannot access child region to verify its state - this is part of the architectural issue');

        // Ideally we would want to do:
        // $childRegion = somehow get the child region reference
        // $this->assertEquals('child1', $childRegion->currentState());
        // $parent->trigger(new stdClass());
        // $this->assertEquals('child2', $childRegion->currentState()); // Would fail!
    }
}
