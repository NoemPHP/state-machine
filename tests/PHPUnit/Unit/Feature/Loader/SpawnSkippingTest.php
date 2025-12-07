<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader skips states without spawn definitions
 */
#[Group('loader')]
#[Group('spawn-processing')]
class SpawnSkippingTest extends TestCase
{
    public function testSkipsStatesWithoutSpawnDefinitions(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $array = [
            'states' => [
                [
                    'name' => 'idle',
                ],
                [
                    'name' => 'processing',
                ],
                [
                    'name' => 'done',
                ],
            ],
            'initial' => 'idle',
        ];

        // Build the region
        $region = $builder->build([
            'loader' => [
                'array' => $array,
            ],
        ]);

        // Get the spawn registry from the builder's ChainMail
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);

        // Verify that no spawn records were created
        $this->assertCount(0, $registry->records, 'Should have no spawn records for states without spawn definitions');
    }
}
