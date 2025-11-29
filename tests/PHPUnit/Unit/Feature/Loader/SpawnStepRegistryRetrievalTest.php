<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: regionSpawnStep retrieves RegionSpawnRegistry from ChainMail
 */
#[Group('loader')]
#[Group('spawn-step-execution')]
class SpawnStepRegistryRetrievalTest extends TestCase
{
    public function testRetrievesRegionSpawnRegistryFromChainMail(): void
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
        
        // Build the region - the BuildStep should retrieve the registry
        $region = $builder->build([
            'loader' => [
                'array' => $array,
            ],
        ]);
        
        // Verify that the registry was retrieved and used by checking that records exist
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $this->assertNotEmpty($registry->records, 'Registry should have spawn records, proving it was retrieved');
        $this->assertCount(1, $registry->records);
    }
}
