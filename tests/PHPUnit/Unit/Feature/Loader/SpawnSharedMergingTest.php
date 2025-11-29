<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Connection;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader merges spawn shared config with default sharing
 */
#[Group('loader')]
#[Group('spawn-processing')]
class SpawnSharedMergingTest extends TestCase
{
    public function testMergesSpawnSharedConfigWithDefaults(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        // Test with partial shared config (only specifying meta: false)
        $array = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn(object $t): bool => true,
                            'region' => [
                                'states' => [
                                    ['name' => 'child'],
                                ],
                                'initial' => 'child',
                            ],
                            'shared' => [
                                'meta' => false,
                            ],
                        ],
                    ],
                ],
            ],
            'initial' => 'parent',
        ];
        
        // Build the region
        $region = $builder->build([
            'loader' => [
                'array' => $array,
            ],
        ]);
        
        // Get the spawn registry
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $spawnRecord = $registry->records[0];
        
        // Verify that when meta is false, RECEIVE_META flag is not set
        $expectedFlags = Connection::DYNAMIC | Connection::RECEIVE_EVENTS | Connection::RECEIVE_ACTIONS;
        $this->assertEquals($expectedFlags, $spawnRecord->connectionFlags, 'Should not include RECEIVE_META when meta is false');
    }
    
    public function testUsesDefaultMetaSharingWhenNotSpecified(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        // Test without shared config at all
        $array = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn(object $t): bool => true,
                            'region' => [
                                'states' => [
                                    ['name' => 'child'],
                                ],
                                'initial' => 'child',
                            ],
                            // No 'shared' property - should use defaults
                        ],
                    ],
                ],
            ],
            'initial' => 'parent',
        ];
        
        // Build the region
        $region = $builder->build([
            'loader' => [
                'array' => $array,
            ],
        ]);
        
        // Get the spawn registry
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $spawnRecord = $registry->records[0];
        
        // Verify that RECEIVE_META flag is set by default
        $expectedFlags = Connection::DYNAMIC | Connection::RECEIVE_EVENTS | Connection::RECEIVE_ACTIONS | Connection::RECEIVE_META;
        $this->assertEquals($expectedFlags, $spawnRecord->connectionFlags, 'Should include RECEIVE_META by default');
    }
}
