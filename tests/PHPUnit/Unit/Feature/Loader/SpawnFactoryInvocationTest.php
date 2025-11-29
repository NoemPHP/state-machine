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
 * Acceptance Criterion: SpawnRegion invokes region factory when guard returns true
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnFactoryInvocationTest extends TestCase
{
    public function testInvokesRegionFactoryWhenGuardReturnsTrue(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $factoryInvoked = false;
        
        // Guard always returns true
        $guard = fn(object $t): bool => true;
        
        $regionFactory = function() use (&$factoryInvoked, $builder): Region {
            $factoryInvoked = true;
            return $builder->newInstance()->setStates('child')->build();
        };
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $region = $builder->build();
        
        $this->assertFalse($factoryInvoked, 'Factory should not be invoked before any actions');
        
        // Trigger action
        $region->trigger(new stdClass());
        
        $this->assertTrue($factoryInvoked, 'Factory should be invoked when guard returns true');
    }

    public function testFactoryInvokedOnlyWhenGuardReturnsTrue(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $factoryInvocationCount = 0;
        $guardCallCount = 0;
        
        $guard = function(object $t) use (&$guardCallCount): bool {
            $guardCallCount++;
            // Only return true on third call
            return $guardCallCount === 3;
        };
        
        $regionFactory = function() use (&$factoryInvocationCount, $builder): Region {
            $factoryInvocationCount++;
            return $builder->newInstance()->setStates('child')->build();
        };
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $region = $builder->build();
        
        // Trigger three times
        $region->trigger(new stdClass());
        $this->assertSame(0, $factoryInvocationCount, 'Factory should not be invoked when guard returns false');
        
        $region->trigger(new stdClass());
        $this->assertSame(0, $factoryInvocationCount, 'Factory should not be invoked when guard returns false');
        
        $region->trigger(new stdClass());
        $this->assertSame(1, $factoryInvocationCount, 'Factory should be invoked when guard returns true');
    }

    public function testFactoryCanBeInvokedMultipleTimes(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $spawnedRegions = [];
        
        // Guard always returns true
        $guard = fn(object $t): bool => true;
        
        $regionFactory = function() use (&$spawnedRegions, $builder): Region {
            $region = $builder->newInstance()->setStates('child')->build();
            $spawnedRegions[] = $region;
            return $region;
        };
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $region = $builder->build();
        
        // Trigger multiple times
        $region->trigger(new stdClass());
        $region->trigger(new stdClass());
        $region->trigger(new stdClass());
        
        // Factory should have been invoked each time
        $this->assertCount(3, $spawnedRegions, 'Factory should be invoked for each trigger when guard returns true');
        
        // Each spawned region should be a unique instance
        $this->assertNotSame($spawnedRegions[0], $spawnedRegions[1]);
        $this->assertNotSame($spawnedRegions[1], $spawnedRegions[2]);
        $this->assertNotSame($spawnedRegions[0], $spawnedRegions[2]);
    }
}
