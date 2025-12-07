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
 * Acceptance Criterion: RegionLoader sets default meta sharing to true
 */
#[Group('loader')]
#[Group('spawn-processing')]
class DefaultMetaSharingTest extends TestCase
{
    public function testSetsDefaultMetaSharingToTrue(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        // Create spawn definition without specifying shared property
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
                            // No 'shared' property - should default to meta: true
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

        // Verify that RECEIVE_META flag is set by default (meta sharing defaults to true)
        $this->assertTrue(
            ($spawnRecord->connectionFlags & Connection::RECEIVE_META) === Connection::RECEIVE_META,
            'Default meta sharing should be true, so RECEIVE_META flag should be set'
        );
    }
}
