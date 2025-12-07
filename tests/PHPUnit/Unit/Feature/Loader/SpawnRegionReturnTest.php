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
 * Acceptance Criterion: SpawnRegion returns spawned region
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnRegionReturnTest extends TestCase
{
    public function testSpawnRegionReturnsSpawnedRegion(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $spawnedRegion = null;

        $guard = fn(object $t): bool => true;

        $regionFactory = function () use (&$spawnedRegion, $builder): Region {
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child');
            $spawnedRegion = $childBuilder->build();
            return $spawnedRegion;
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $parentRegion = $builder->build();

        // The spawned region should be returned and available
        $this->assertNull($spawnedRegion, 'Region not spawned yet');

        // Trigger spawn
        $parentRegion->trigger(new stdClass());

        // The factory should have been called and returned a region
        $this->assertInstanceOf(Region::class, $spawnedRegion);
        $this->assertTrue($spawnedRegion->isInState('child'));
    }

    public function testSpawnRegionReturnsNullWhenGuardFalse(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $factoryCalled = false;

        $guard = fn(object $t): bool => false; // Guard always returns false

        $regionFactory = function () use (&$factoryCalled, $builder): Region {
            $factoryCalled = true;
            return $builder->newInstance()->setStates('child')->build();
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $parentRegion = $builder->build();

        // Trigger - guard returns false, so factory should not be called
        $parentRegion->trigger(new stdClass());

        $this->assertFalse($factoryCalled, 'Factory should not be called when guard returns false');
    }

    public function testEachSpawnReturnsUniqueRegion(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $spawnedRegions = [];

        $guard = fn(object $t): bool => true;

        $regionFactory = function () use (&$spawnedRegions, $builder): Region {
            $childBuilder = $builder->newInstance();
            $childBuilder->setStates('child');
            $region = $childBuilder->build();
            $spawnedRegions[] = $region;
            return $region;
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $parentRegion = $builder->build();

        // Trigger multiple spawns
        $parentRegion->trigger(new stdClass());
        $parentRegion->trigger(new stdClass());
        $parentRegion->trigger(new stdClass());

        // Each spawn should return a unique region instance
        $this->assertCount(3, $spawnedRegions);
        $this->assertNotSame($spawnedRegions[0], $spawnedRegions[1]);
        $this->assertNotSame($spawnedRegions[1], $spawnedRegions[2]);
        $this->assertNotSame($spawnedRegions[0], $spawnedRegions[2]);
    }
}
