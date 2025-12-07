<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: SpawnRegion creates Connection with parent region and sub-region
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnConnectionCreationTest extends TestCase
{
    public function testSpawnedRegionReceivesEventsFromParent(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $childReceivedEvents = 0;
        $spawnedRegion = null;
        $spawnCount = 0;

        // Guard returns true only on first call
        $guard = function (object $t) use (&$spawnCount): bool {
            $spawnCount++;
            return $spawnCount === 1;
        };

        $regionFactory = function () use (&$spawnedRegion, &$childReceivedEvents, $builder): Region {
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child');
            $childBuilder->onAction('child', function (object $trigger) use (&$childReceivedEvents) {
                $childReceivedEvents++;
                return 'child';
            });
            $spawnedRegion = $childBuilder->build();
            return $spawnedRegion;
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $parentRegion = $builder->build();

        // First trigger spawns the child - child receives the spawn trigger due to RECEIVE_ACTIONS
        $parentRegion->trigger(new stdClass());
        $this->assertNotNull($spawnedRegion, 'Region should have been spawned');
        $this->assertSame(1, $childReceivedEvents, 'Child should receive the action that triggered its spawn');

        // Second trigger should also propagate to spawned child (guard returns false, no new spawn)
        $parentRegion->trigger(new stdClass());
        $this->assertSame(2, $childReceivedEvents, 'Spawned region should receive subsequent events from parent');
    }

    public function testSpawnedRegionIsConnectedToParent(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $spawnedRegion = null;

        $guard = fn(object $t): bool => true;

        $regionFactory = function () use (&$spawnedRegion, $builder): Region {
            $spawnedRegion = $builder->newInstance()->setStates('child')->build();
            return $spawnedRegion;
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $parentRegion = $builder->build();

        // Trigger to spawn
        $parentRegion->trigger(new stdClass());

        $this->assertNotNull($spawnedRegion, 'Region should have been spawned');
        // The spawned region should be a valid Region instance
        $this->assertInstanceOf(Region::class, $spawnedRegion);
    }

    public function testMultipleSpawnsCreateMultipleIndependentRegions(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $spawnedRegions = [];
        $childActions = [];

        $guard = fn(object $t): bool => true;

        $regionFactory = function () use (&$spawnedRegions, &$childActions, $builder): Region {
            $index = count($spawnedRegions);
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child');
            $childBuilder->onAction('child', function (object $trigger) use ($index, &$childActions) {
                $childActions[$index] = true;
                return 'child';
            });
            $region = $childBuilder->build();
            $spawnedRegions[] = $region;
            return $region;
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $parentRegion = $builder->build();

        // Trigger multiple times to spawn multiple regions
        $parentRegion->trigger(new stdClass());
        $parentRegion->trigger(new stdClass());
        $parentRegion->trigger(new stdClass());

        // Should have spawned 3 regions
        $this->assertCount(3, $spawnedRegions);

        // Each should be a unique instance
        $this->assertNotSame($spawnedRegions[0], $spawnedRegions[1]);
        $this->assertNotSame($spawnedRegions[1], $spawnedRegions[2]);
        $this->assertNotSame($spawnedRegions[0], $spawnedRegions[2]);

        // Trigger parent again - all three children should receive the event
        $parentRegion->trigger(new stdClass());

        $this->assertTrue($childActions[0] ?? false, 'First spawned child should receive event');
        $this->assertTrue($childActions[1] ?? false, 'Second spawned child should receive event');
        $this->assertTrue($childActions[2] ?? false, 'Third spawned child should receive event');
    }
}
