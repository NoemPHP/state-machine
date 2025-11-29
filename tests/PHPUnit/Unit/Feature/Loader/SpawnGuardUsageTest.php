<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader uses guard from spawn definition
 */
#[Group('loader')]
#[Group('spawn-processing')]
class SpawnGuardUsageTest extends TestCase
{
    public function testUsesGuardFromSpawnDefinition(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        // Create a specific guard that we can verify
        $expectedGuard = fn(object $t): bool => isset($t->spawn);
        
        $array = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => $expectedGuard,
                            'region' => [
                                'states' => [
                                    ['name' => 'child'],
                                ],
                                'initial' => 'child',
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
        
        // Get the spawn registry from the builder's ChainMail
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        
        // Verify that the guard in the spawn record is the one we provided
        $this->assertCount(1, $registry->records);
        $spawnRecord = $registry->records[0];
        
        // Verify the guard is the same one we provided
        $this->assertSame($expectedGuard, $spawnRecord->guard);
    }
}
