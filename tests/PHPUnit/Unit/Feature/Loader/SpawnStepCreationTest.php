<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader creates region spawn step for each spawn definition
 */
#[Group('loader')]
#[Group('spawn-processing')]
class SpawnStepCreationTest extends TestCase
{
    public function testCreatesRegionSpawnStepForEachSpawnDefinition(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        // Configuration with multiple spawn definitions
        $array = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn(object $t): bool => true,
                            'region' => [
                                'states' => [['name' => 'child1']],
                                'initial' => 'child1',
                            ],
                        ],
                        [
                            'guard' => fn(object $t): bool => false,
                            'region' => [
                                'states' => [['name' => 'child2']],
                                'initial' => 'child2',
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

        // Verify that a spawn record was created for each spawn definition
        $this->assertCount(2, $registry->records, 'Should have two spawn records for two spawn definitions');
    }
}
