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
 * Acceptance Criterion: SpawnRegion returns null when guard returns false
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnGuardFalseTest extends TestCase
{
    public function testReturnsNullWhenGuardReturnsFalse(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $factoryCalled = false;

        // Guard always returns false
        $guard = fn(object $t): bool => false;

        $regionFactory = function () use (&$factoryCalled, $builder): Region {
            $factoryCalled = true;
            return $builder->newInstance()->setStates('child')->build();
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $region = $builder->build();

        // Trigger action
        $region->trigger(new stdClass());

        // Factory should not have been called
        $this->assertFalse($factoryCalled, 'Factory should not be called when guard returns false');
    }

    public function testPreventsSpawningWhenGuardReturnsFalse(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $spawnedRegions = [];

        $guard = fn(object $t): bool => false;

        $regionFactory = function () use (&$spawnedRegions, $builder): Region {
            $region = $builder->newInstance()->setStates('child')->build();
            $spawnedRegions[] = $region;
            return $region;
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $region = $builder->build();

        // Trigger multiple actions
        $region->trigger(new stdClass());
        $region->trigger(new stdClass());
        $region->trigger(new stdClass());

        // No regions should have been spawned
        $this->assertCount(0, $spawnedRegions, 'No regions should spawn when guard returns false');
    }

    public function testConditionalSpawningBasedOnGuardLogic(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $spawnCount = 0;

        // Guard checks trigger property
        $guard = function (object $t) use (&$spawnCount): bool {
            return property_exists($t, 'shouldSpawn') && $t->shouldSpawn === true;
        };

        $regionFactory = function () use (&$spawnCount, $builder): Region {
            $spawnCount++;
            return $builder->newInstance()->setStates('child')->build();
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $region = $builder->build();

        // Trigger with shouldSpawn=false
        $payload1 = new stdClass();
        $payload1->shouldSpawn = false;
        $region->trigger($payload1);
        $this->assertSame(0, $spawnCount, 'Should not spawn when condition not met');

        // Trigger without property
        $payload2 = new stdClass();
        $region->trigger($payload2);
        $this->assertSame(0, $spawnCount, 'Should not spawn when property missing');

        // Trigger with shouldSpawn=true
        $payload3 = new stdClass();
        $payload3->shouldSpawn = true;
        $region->trigger($payload3);
        $this->assertSame(1, $spawnCount, 'Should spawn when condition met');
    }
}
