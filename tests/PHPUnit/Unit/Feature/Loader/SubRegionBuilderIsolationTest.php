<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader creates new builder instance for sub-region
 */
#[Group('loader')]
#[Group('spawn-processing')]
class SubRegionBuilderIsolationTest extends TestCase
{
    public function testCreatesNewBuilderInstanceForSubRegion(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $array = [
            'states' => [
                [
                    'name' => 'parent_state',
                ],
                [
                    'name' => 'parent_with_spawn',
                    'spawn' => [
                        [
                            'guard' => fn(object $t): bool => true,
                            'region' => [
                                'states' => [
                                    ['name' => 'child_state'],
                                ],
                                'initial' => 'child_state',
                            ],
                        ],
                    ],
                ],
            ],
            'initial' => 'parent_state',
        ];

        // Build the parent region
        $parentRegion = $builder->build([
            'loader' => [
                'array' => $array,
            ],
        ]);

        // Get the spawn registry and invoke the factory
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $spawnRecord = $registry->records[0];

        // Create the sub-region
        $subRegion = ($spawnRecord->regionFactory)();

        // Verify isolation: sub-region should only have its own states
        $this->assertTrue($subRegion->isInState('child_state'), 'Sub-region should have child_state');

        // Sub-region should NOT have parent states
        $this->assertFalse($subRegion->isInState('parent_state'), 'Sub-region should not have parent states');
        $this->assertFalse($subRegion->isInState('parent_with_spawn'), 'Sub-region should not have parent states');

        // Parent should only have its own states
        $this->assertTrue($parentRegion->isInState('parent_state'), 'Parent should have its own states');
        $this->assertFalse($parentRegion->isInState('child_state'), 'Parent should not have sub-region states');
    }
}
