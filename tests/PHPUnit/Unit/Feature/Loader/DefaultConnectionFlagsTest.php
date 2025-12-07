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
 * Acceptance Criterion: RegionLoader applies DYNAMIC, RECEIVE_EVENTS, and RECEIVE_ACTIONS flags by default
 */
#[Group('loader')]
#[Group('spawn-processing')]
class DefaultConnectionFlagsTest extends TestCase
{
    public function testAppliesDefaultConnectionFlags(): void
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

        // Get the spawn registry
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $spawnRecord = $registry->records[0];

        // Verify that DYNAMIC, RECEIVE_EVENTS, and RECEIVE_ACTIONS flags are set
        $this->assertTrue(
            ($spawnRecord->connectionFlags & Connection::DYNAMIC) === Connection::DYNAMIC,
            'DYNAMIC flag should be set by default'
        );
        $this->assertTrue(
            ($spawnRecord->connectionFlags & Connection::RECEIVE_EVENTS) === Connection::RECEIVE_EVENTS,
            'RECEIVE_EVENTS flag should be set by default'
        );
        $this->assertTrue(
            ($spawnRecord->connectionFlags & Connection::RECEIVE_ACTIONS) === Connection::RECEIVE_ACTIONS,
            'RECEIVE_ACTIONS flag should be set by default'
        );
    }
}
