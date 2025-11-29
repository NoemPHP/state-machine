<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader\SpawnExecution;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SpawnRegion tracks spawned regions and returns spawned region or null based on guard evaluation
 * 
 * Intent: Provides spawn outcome visibility through return values and maintains spawn records for runtime behavior
 * 
 * Replaces 10 specs:
 * - spawn-execution (1 spec): Return value
 * - spawn-registry (3 specs): Registry storage
 * - spawn-record (6 specs): Record structure
 */
#[Group('loader')]
#[Group('spawn-execution')]
class TrackingTest extends TestCase
{
    #[Test]
    public function testReturnsSpawnedRegionWhenGuardPasses(): void
    {
        // Tests: SpawnRegion returns spawned region when successful
        $childSpawned = false;
        
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
                                        'onEnter' => function () use (&$childSpawned) {
                                            $childSpawned = true;
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

        // Spawned region should exist and function
        $this->assertTrue($childSpawned, 'Spawned region should be returned and functional');
    }

    #[Test]
    public function testReturnsNullWhenGuardFails(): void
    {
        // Tests: SpawnRegion returns null when guard returns false
        $childSpawned = false;
        
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => false, // Guard fails
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onEnter' => function () use (&$childSpawned) {
                                            $childSpawned = true;
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

        // Attempt spawn - should fail
        $region->trigger(new \stdClass());
        $region->dispatch();

        // No region should be spawned
        $this->assertFalse($childSpawned, 'Should return null when guard fails');
    }

    #[Test]
    public function testReturnsNullWhenParameterIncompatible(): void
    {
        // Tests: SpawnRegion returns null when parameter types incompatible
        $childSpawned = false;
        
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            // Guard expects DateTime
                            'guard' => function (\DateTime $t) {
                                return true;
                            },
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'onEnter' => function () use (&$childSpawned) {
                                            $childSpawned = true;
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

        // Trigger with incompatible type
        $region->trigger(new \stdClass());
        $region->dispatch();

        // No region should be spawned
        $this->assertFalse($childSpawned, 'Should return null when parameter incompatible');
    }

    #[Test]
    public function testRegistryStoresSpawnRecords(): void
    {
        // Tests: RegionSpawnRegistry stores spawn records
        // Tests: RegionSpawnRegistry addRecord stores spawn record
        $config = [
            'states' => [
                [
                    'name' => 'parent1',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child1']]],
                        ],
                    ],
                ],
                [
                    'name' => 'parent2',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child2']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        // Verify registry contains spawn records
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        
        $this->assertCount(
            2,
            $registry->records,
            'Registry should store spawn records'
        );
    }

    #[Test]
    public function testRecordsContainAllConfiguration(): void
    {
        // Tests: RegionSpawnRecord stores all required fields:
        // - parent region reference
        // - parent state name
        // - region factory closure
        // - guard closure
        // - connection flags
        
        $config = [
            'states' => [
                [
                    'name' => 'testState',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child']]],
                            'shared' => ['meta' => false],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        // Get spawn record from registry
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $record = $registry->records[0];

        // Verify record contains all necessary configuration
        $this->assertSame($region, $record->parentRegion, 'Record should store parent region');
        $this->assertSame('testState', $record->parentStateName, 'Record should store state name');
        $this->assertIsCallable($record->regionFactory, 'Record should store factory');
        $this->assertIsCallable($record->guard, 'Record should store guard');
        $this->assertIsInt($record->connectionFlags, 'Record should store flags');
    }

    #[Test]
    public function testRegistryInitializesEmpty(): void
    {
        // Tests: RegionSpawnRegistry initializes with empty records array
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        // Get registry before any spawn definitions
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        
        $this->assertCount(
            0,
            $registry->records,
            'Registry should initialize empty'
        );
    }

    #[Test]
    public function testRegistryAccumulatesMultipleRecords(): void
    {
        // Tests: Registry accumulates spawn records from multiple states
        $config = [
            'states' => [
                [
                    'name' => 'state1',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child1']]],
                        ],
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child2']]],
                        ],
                    ],
                ],
                [
                    'name' => 'state2',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child3']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        // Verify registry accumulated all records
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        
        $this->assertCount(
            3,
            $registry->records,
            'Registry should accumulate multiple spawn records'
        );
    }

    #[Test]
    public function testRecordStoresCorrectParentState(): void
    {
        // Tests: Record correctly identifies which state triggers spawning
        $config = [
            'states' => [
                ['name' => 'noSpawn'],
                [
                    'name' => 'spawningState',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $record = $registry->records[0];

        $this->assertSame(
            'spawningState',
            $record->parentStateName,
            'Record should store correct parent state name'
        );
    }

    #[Test]
    public function testRecordFactoryCreatesWorkingRegion(): void
    {
        // Tests: Region factory in record creates functional sub-region
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => [
                                'states' => [
                                    ['name' => 'childState1'],
                                    ['name' => 'childState2'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        // Get factory from record
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $factory = $registry->records[0]->regionFactory;

        // Invoke factory
        $spawnedRegion = $factory();

        // Verify spawned region is functional
        $this->assertInstanceOf(
            \Noem\State\Region::class,
            $spawnedRegion,
            'Record factory should create working region'
        );
    }

    #[Test]
    public function testRecordGuardEvaluatesCorrectly(): void
    {
        // Tests: Guard stored in record evaluates properly
        $guardEvaluated = false;
        
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => function ($t) use (&$guardEvaluated) {
                                $guardEvaluated = true;
                                return $t->shouldSpawn;
                            },
                            'region' => ['states' => [['name' => 'child']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        // Trigger with guard condition
        $trigger = new \stdClass();
        $trigger->shouldSpawn = true;
        $region->trigger($trigger);

        $this->assertTrue($guardEvaluated, 'Guard from record should be evaluated');
    }

    #[Test]
    public function testRecordUsesDefaultFlagsWhenNotProvided(): void
    {
        // Tests: RegionSpawnRecord uses default connection flags when not provided
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child']]],
                            // No explicit flags or shared config
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $record = $registry->records[0];

        // Default flags should include DYNAMIC, RECEIVE_EVENTS, RECEIVE_ACTIONS, RECEIVE_META
        $expectedFlags = \Noem\State\Connection::DYNAMIC
            | \Noem\State\Connection::RECEIVE_EVENTS
            | \Noem\State\Connection::RECEIVE_ACTIONS
            | \Noem\State\Connection::RECEIVE_META;

        $this->assertSame(
            $expectedFlags,
            $record->connectionFlags,
            'Record should use default flags when not provided'
        );
    }
}
