<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader processes spawn definitions from state configuration
 */
#[Group('loader')]
#[Group('spawn-processing')]
class SpawnDefinitionProcessingTest extends TestCase
{
    public function testProcessesSpawnDefinitionsFromStateConfiguration(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
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
        
        // Verify that spawn records were created
        $this->assertCount(1, $registry->records, 'Should have one spawn record');
        
        $spawnRecord = $registry->records[0];
        $this->assertSame($region, $spawnRecord->parentRegion);
        $this->assertEquals('parent', $spawnRecord->parentStateName);
    }
}
