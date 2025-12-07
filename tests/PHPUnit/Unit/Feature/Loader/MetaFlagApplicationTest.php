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
 * Acceptance Criterion: RegionLoader includes RECEIVE_META flag when meta sharing enabled
 */
#[Group('loader')]
#[Group('spawn-processing')]
class MetaFlagApplicationTest extends TestCase
{
    public function testIncludesReceiveMetaFlagWhenMetaSharingEnabled(): void
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
                            'shared' => [
                                'meta' => true,
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

        // Verify that RECEIVE_META flag is set when meta sharing is enabled
        $this->assertTrue(
            ($spawnRecord->connectionFlags & Connection::RECEIVE_META) === Connection::RECEIVE_META,
            'RECEIVE_META flag should be set when meta sharing is enabled'
        );
    }

    public function testExcludesReceiveMetaFlagWhenMetaSharingDisabled(): void
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

        // Verify that RECEIVE_META flag is NOT set when meta sharing is disabled
        $this->assertFalse(
            ($spawnRecord->connectionFlags & Connection::RECEIVE_META) === Connection::RECEIVE_META,
            'RECEIVE_META flag should NOT be set when meta sharing is disabled'
        );
    }
}
