<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Core\Region;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: Connected regions via shared ChainMail do not duplicate action callbacks
 *
 * This integration test verifies the fix for the bug where child regions created through
 * newInstance() with shared ChainMail instances would receive duplicate action callbacks.
 *
 * The root cause was that RegionBuilder.build() invoked features every time, and features
 * registered middleware on shared chains. When parent and child shared ChainMail via newInstance(),
 * the second build() call would invoke features again, duplicating middleware registration.
 *
 * The fix: FeatureRegistry.resolve(ChainMail) now tracks invocations per ChainMail instance,
 * ensuring features are invoked exactly once per container, preventing duplicate middleware.
 */
#[Group('region')]
#[Group('action-chain-integration')]
#[Group('integration')]
class NoActionDuplicationWithSharedChainMailTest extends TestCase
{
    public function testChildRegionActionCallbackFiresOnlyOnce(): void
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
                                    'action' => [
                                        ['run' => function($t) use (&$actionLog) {
                                            $actionLog[] = 'child1';
                                            return 'child1';
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

        // Action should fire exactly once, not twice
        $this->assertEquals(['child1'], $actionLog,
            'Child action callback should fire exactly once despite shared ChainMail');
    }

    public function testChildRegionLifecycleCallbacksFireOnlyOnce(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $lifecycleLog = [];

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
                                        ['run' => function($t) use (&$lifecycleLog) {
                                            $lifecycleLog[] = 'exit_child1';
                                        }],
                                    ],
                                ],
                                [
                                    'name' => 'child2',
                                    'onEnter' => [
                                        ['run' => function($t) use (&$lifecycleLog) {
                                            $lifecycleLog[] = 'enter_child2';
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

        // Lifecycle callbacks should fire exactly once each
        $this->assertEquals(['exit_child1', 'enter_child2'], $lifecycleLog,
            'Lifecycle callbacks should fire exactly once despite shared ChainMail');
    }

    public function testMultipleChildRegionsEachReceiveActionsOnce(): void
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
                                    'name' => 'childA',
                                    'action' => [
                                        ['run' => function($t) use (&$actionLog) {
                                            $actionLog[] = 'childA';
                                            return 'childA';
                                        }],
                                    ],
                                ],
                            ],
                            'initial' => 'childA',
                        ],
                        [
                            'states' => [
                                [
                                    'name' => 'childB',
                                    'action' => [
                                        ['run' => function($t) use (&$actionLog) {
                                            $actionLog[] = 'childB';
                                            return 'childB';
                                        }],
                                    ],
                                ],
                            ],
                            'initial' => 'childB',
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

        // Each child should receive action exactly once (order may vary)
        $this->assertCount(2, $actionLog,
            'Both children should receive actions');
        $this->assertContains('childA', $actionLog,
            'ChildA should receive action exactly once');
        $this->assertContains('childB', $actionLog,
            'ChildB should receive action exactly once');
    }

    public function testDeeplyNestedRegionsNoActionDuplication(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $actionLog = [];

        $config = [
            'states' => [
                [
                    'name' => 'level1',
                    'regions' => [
                        [
                            'states' => [
                                [
                                    'name' => 'level2',
                                    'action' => [
                                        ['run' => function($t) use (&$actionLog) {
                                            $actionLog[] = 'level2';
                                            return 'level2';
                                        }],
                                    ],
                                    'regions' => [
                                        [
                                            'states' => [
                                                [
                                                    'name' => 'level3',
                                                    'action' => [
                                                        ['run' => function($t) use (&$actionLog) {
                                                            $actionLog[] = 'level3';
                                                            return 'level3';
                                                        }],
                                                    ],
                                                ],
                                            ],
                                            'initial' => 'level3',
                                        ],
                                    ],
                                ],
                            ],
                            'initial' => 'level2',
                        ],
                    ],
                ],
            ],
            'initial' => 'level1',
        ];

        $parentRegion = $builder->build([
            'loader' => ['array' => $config],
        ]);

        // Trigger through top level
        $parentRegion->trigger(new stdClass());

        // Each level should receive action exactly once (order may vary)
        $this->assertCount(2, $actionLog,
            'Both nested levels should receive actions');
        $this->assertContains('level2', $actionLog,
            'Level 2 should receive action exactly once');
        $this->assertContains('level3', $actionLog,
            'Level 3 should receive action exactly once');
    }
}
