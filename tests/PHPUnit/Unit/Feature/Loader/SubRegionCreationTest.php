<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader creates sub-region from region definition
 */
#[Group('loader')]
#[Group('spawn-processing')]
class SubRegionCreationTest extends TestCase
{
    public function testCreatesSubRegionFromRegionDefinition(): void
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
                                    ['name' => 'child_state_1'],
                                    ['name' => 'child_state_2'],
                                ],
                                'initial' => 'child_state_1',
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
        
        // Get the spawn registry and invoke the factory
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $spawnRecord = $registry->records[0];
        
        // Invoke the factory to create the sub-region
        $subRegion = ($spawnRecord->regionFactory)();
        
        // Verify the sub-region was created with the correct configuration
        $this->assertNotNull($subRegion, 'Factory should create a sub-region');
        $this->assertTrue($subRegion->isInState('child_state_1'), 'Sub-region should start in initial state child_state_1');
        
        // Verify it's a valid Region instance
        $this->assertInstanceOf(\Noem\State\Region::class, $subRegion);
    }
}
