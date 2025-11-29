<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Region;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Connected regions process actions and update their state when receiving forwarded triggers
 *
 * This integration test verifies that hierarchical state machines work correctly:
 * - Child regions receive action triggers ✓
 * - Child action callbacks execute ✓
 * - Child guards are evaluated ✓
 * - Child state updates ✓ FIXED!
 * - Child DoTransition called ✓ FIXED!
 *
 * FIX IMPLEMENTED: Region.processOneAction() method now properly handles:
 * 1. Action chain execution (includes callback firing and guard evaluation)
 * 2. State updates when action/transition returns new state
 * 3. DoTransition chain execution for lifecycle callbacks
 *
 * DispatchAction now calls processOneAction() directly on child regions instead of
 * just calling the action chain, ensuring complete state machine semantics.
 */
#[Group('region')]
#[Group('action-chain-integration')]
#[Group('integration')]
class ConnectedRegionStateUpdateTest extends TestCase
{
    public function testChildRegionTransitionsNeverExecuteWhenTriggeredThroughParent(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $transitionLog = [];

        // Build parent with child that has a guaranteed transition
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
                                    'onExit' => [
                                        ['run' => function($t) use (&$transitionLog) {
                                            $transitionLog[] = 'exit_child1';
                                        }],
                                    ],
                                ],
                                [
                                    'name' => 'child2',
                                    'onEnter' => [
                                        ['run' => function($t) use (&$transitionLog) {
                                            $transitionLog[] = 'enter_child2';
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

        $parentRegion = $builder->build([
            'loader' => ['array' => $config],
        ]);

        // Trigger through parent
        $parentRegion->trigger(new stdClass());

        // FIXED: Transition callbacks now fire because DoTransition IS called on child
        $this->assertEquals(['exit_child1', 'enter_child2'], $transitionLog,
            'Child transition lifecycle callbacks (onExit/onEnter) execute correctly! ' .
            'This confirms DoTransition chain is properly called on child regions.');
    }

    public function testChildRegionActionsFireButStateDoesNotChange(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $actionLog = [];

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
                                        ['run' => function($t) use (&$actionLog) {
                                            $actionLog[] = 'child1';
                                            return 'child1';
                                        }],
                                    ],
                                ],
                                [
                                    'name' => 'child2',
                                    'action' => [
                                        ['run' => function($t) use (&$actionLog) {
                                            $actionLog[] = 'child2';
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

        $parentRegion = $builder->build([
            'loader' => ['array' => $config],
        ]);

        // First trigger - child1 action should fire
        $parentRegion->trigger(new stdClass());
        $this->assertEquals(['child1'], $actionLog, 'First trigger: child1 action fires');

        // Second trigger - child2 action SHOULD fire (and now does!)
        $parentRegion->trigger(new stdClass());

        // FIXED: child2 action fires because transition properly occurred
        $this->assertEquals(['child1', 'child2'], $actionLog,
            'Child properly transitioned to child2, so child2 action fires! ' .
            'This confirms child state IS updated when triggered through parent.');
    }

    public function testChildRegionWithConditionalTransitionNowWorksCorrectly(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $actionLog = [];
        $transitionLog = [];

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'regions' => [
                        [
                            'states' => [
                                [
                                    'name' => 'waiting',
                                    'transitions' => [
                                        [
                                            'target' => 'active',
                                            'guard' => fn(object $t): bool => ($t->activate ?? false) === true,
                                        ],
                                    ],
                                    'action' => [
                                        ['run' => function($t) use (&$actionLog) {
                                            $actionLog[] = 'waiting';
                                            return 'waiting';
                                        }],
                                    ],
                                    'onExit' => [
                                        ['run' => function($t) use (&$transitionLog) {
                                            $transitionLog[] = 'exit_waiting';
                                        }],
                                    ],
                                ],
                                [
                                    'name' => 'active',
                                    'action' => [
                                        ['run' => function($t) use (&$actionLog) {
                                            $actionLog[] = 'active';
                                            return 'active';
                                        }],
                                    ],
                                    'onEnter' => [
                                        ['run' => function($t) use (&$transitionLog) {
                                            $transitionLog[] = 'enter_active';
                                        }],
                                    ],
                                ],
                            ],
                            'initial' => 'waiting',
                        ],
                    ],
                ],
            ],
            'initial' => 'parent',
        ];

        $parentRegion = $builder->build([
            'loader' => ['array' => $config],
        ]);

        // First trigger without activation flag - should stay in waiting
        $trigger1 = new stdClass();
        $parentRegion->trigger($trigger1);
        $this->assertEquals(['waiting'], $actionLog, 'First trigger: waiting action fires');
        $this->assertEquals([], $transitionLog, 'No transition should occur');

        // Second trigger with activation flag - should transition to active
        $trigger2 = new stdClass();
        $trigger2->activate = true;
        $parentRegion->trigger($trigger2);

        // Note: waiting action fires BEFORE transition, then transition happens
        $this->assertEquals(['waiting', 'waiting'], $actionLog,
            'Second trigger: waiting action fires before transition');
        $this->assertEquals(['exit_waiting', 'enter_active'], $transitionLog,
            'Transition callbacks fire: exit waiting, enter active');

        // Third trigger - now active state's action should fire
        $parentRegion->trigger(new stdClass());
        $this->assertEquals(['waiting', 'waiting', 'active'], $actionLog,
            'Third trigger: active action fires, confirming successful transition');
    }
}
