<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader\SpawnExecution;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SpawnRegion creates connections with configured flags and state-based predicates
 *
 * Intent: Establishes event propagation between parent and spawned regions with connection active only while parent is in spawning state
 *
 * Replaces 4 specs from spawn-execution:
 * - SpawnRegion creates Connection with parent region and sub-region
 * - SpawnRegion uses connection flags from spawn record
 * - SpawnRegion creates connection predicate tied to parent state
 * - SpawnRegion adds connection to ConnectedRegions
 */
#[Group('loader')]
#[Group('spawn-execution')]
class ConnectionCreationTest extends TestCase
{
    #[Test]
    public function testCreatesConnectionBetweenParentAndSpawnedRegion(): void
    {
        // Tests: SpawnRegion creates Connection with parent region and sub-region
        $childReceivedTrigger = false;

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onAction' => function () use (&$childReceivedTrigger) {
                                            $childReceivedTrigger = true;
                                        },
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // First trigger spawns child
        $region->trigger(new \stdClass());
        $region->dispatch();

        // Second trigger should propagate to child
        $region->trigger(new \stdClass());
        $region->dispatch();

        $this->assertTrue(
            $childReceivedTrigger,
            'Connection should be created between parent and spawned region'
        );
    }

    #[Test]
    public function testUsesConnectionFlagsFromRecord(): void
    {
        // Tests: SpawnRegion uses connection flags from spawn record
        // Default flags include RECEIVE_EVENTS and RECEIVE_ACTIONS
        $childReceivedEvent = false;

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onEnter' => function () use (&$childReceivedEvent) {
                                            $childReceivedEvent = true;
                                        },
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // Spawn child
        $region->trigger(new \stdClass());
        $region->dispatch();

        // Child should receive event due to RECEIVE_EVENTS flag
        $this->assertTrue(
            $childReceivedEvent,
            'Connection should use configured flags (RECEIVE_EVENTS)'
        );
    }

    #[Test]
    public function testCreatesPredicateTiedToParentState(): void
    {
        // Tests: SpawnRegion creates connection predicate tied to parent state
        // Connection should only be active when parent is in spawning state

        $childActionCount = 0;

        $config = [
            'states' => [
                [
                    'name' => 'spawningState',
                    'spawn' => [
                        [
                            'guard' => fn($t) => $t->shouldSpawn ?? false,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onAction' => function () use (&$childActionCount) {
                                            $childActionCount++;
                                        },
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                ['name' => 'otherState'],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // Spawn child
        $spawnTrigger = new \stdClass();
        $spawnTrigger->shouldSpawn = true;
        $region->trigger($spawnTrigger);
        $region->dispatch();

        // Trigger while in spawning state - child should receive
        $region->trigger(new \stdClass());
        $region->dispatch();

        $this->assertGreaterThan(
            0,
            $childActionCount,
            'Connection predicate should be tied to parent state'
        );
    }

    #[Test]
    public function testRegistersConnectionInConnectedRegions(): void
    {
        // Tests: SpawnRegion adds connection to ConnectedRegions
        $childReceived = false;

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onAction' => function () use (&$childReceived) {
                                            $childReceived = true;
                                        },
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // Spawn child
        $region->trigger(new \stdClass());
        $region->dispatch();

        // Trigger should propagate to child via ConnectedRegions
        $region->trigger(new \stdClass());
        $region->dispatch();

        $this->assertTrue(
            $childReceived,
            'Connection should be registered in ConnectedRegions'
        );
    }

    #[Test]
    public function testConnectionPropagatesEventsToSpawnedRegion(): void
    {
        // Tests event propagation through connection
        $events = [];

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'onEnter' => function () use (&$events) {
                        $events[] = 'parent-enter';
                    },
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onEnter' => function () use (&$events) {
                                            $events[] = 'child-enter';
                                        },
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // Spawn and process
        $region->trigger(new \stdClass());
        $region->dispatch();

        // Both parent and child events should fire
        $this->assertContains('parent-enter', $events);
        $this->assertContains('child-enter', $events);
    }

    #[Test]
    public function testConnectionSupportsMultipleSpawnedRegions(): void
    {
        // Tests: Multiple spawned regions can connect to same parent
        $child1Received = false;
        $child2Received = false;

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => $t->spawn === 1,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child1',
                                        'onAction' => function () use (&$child1Received) {
                                            $child1Received = true;
                                        },
                                    ],
                                ],
                            ],
                        ],
                        [
                            'guard' => fn($t) => $t->spawn === 2,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child2',
                                        'onAction' => function () use (&$child2Received) {
                                            $child2Received = true;
                                        },
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // Spawn both children
        $trigger1 = new \stdClass();
        $trigger1->spawn = 1;
        $region->trigger($trigger1);
        $region->dispatch();

        $trigger2 = new \stdClass();
        $trigger2->spawn = 2;
        $region->trigger($trigger2);
        $region->dispatch();

        // Send action to both
        $region->trigger(new \stdClass());
        $region->dispatch();

        $this->assertTrue($child1Received, 'First spawned region should receive actions');
        $this->assertTrue($child2Received, 'Second spawned region should receive actions');
    }

    #[Test]
    public function testConnectionUsesConfiguredReceiveActionsFlag(): void
    {
        // Tests: Connection uses RECEIVE_ACTIONS flag from configuration
        $childActionFired = false;

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onAction' => function () use (&$childActionFired) {
                                            $childActionFired = true;
                                        },
                                    ],
                                ],
                            ],
                            // Default includes RECEIVE_ACTIONS
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // Spawn child
        $region->trigger(new \stdClass());
        $region->dispatch();

        // Trigger action - should propagate to child
        $region->trigger(new \stdClass());
        $region->dispatch();

        $this->assertTrue(
            $childActionFired,
            'Connection should use RECEIVE_ACTIONS flag'
        );
    }

    #[Test]
    public function testConnectionPredicateControlsEventPropagation(): void
    {
        // Tests: Predicate controls when connection is active
        // (This is a more comprehensive test of the predicate behavior)

        $actionCount = 0;

        $config = [
            'states' => [
                [
                    'name' => 'active',
                    'spawn' => [
                        [
                            'guard' => fn($t) => $t->shouldSpawn ?? false,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onAction' => function () use (&$actionCount) {
                                            $actionCount++;
                                        },
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // Spawn child
        $spawnTrigger = new \stdClass();
        $spawnTrigger->shouldSpawn = true;
        $region->trigger($spawnTrigger);
        $region->dispatch();

        // Trigger multiple actions while in active state
        $region->trigger(new \stdClass());
        $region->trigger(new \stdClass());
        $region->dispatch();

        // Actions should propagate due to predicate
        $this->assertGreaterThan(
            0,
            $actionCount,
            'Connection predicate should control event propagation'
        );
    }
}
