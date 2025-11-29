<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

class TypedTrigger {
    public function __construct(public string $value) {}
}

/**
 * Acceptance Criterion: SpawnRegion returns null when parameter incompatible
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnParameterIncompatibilityTest extends TestCase
{
    public function testReturnsNullWhenParameterIncompatible(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $guardCalled = false;
        $factoryCalled = false;
        
        // Guard expects TypedTrigger specifically
        $guard = function(TypedTrigger $t) use (&$guardCalled): bool {
            $guardCalled = true;
            return true;
        };
        
        $regionFactory = function() use (&$factoryCalled, $builder): Region {
            $factoryCalled = true;
            return $builder->newInstance()->setStates('child')->build();
        };
        
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );
        
        $region = $builder->build();
        
        // Trigger with incompatible type
        $region->trigger(new stdClass());
        
        // Neither guard nor factory should have been called
        $this->assertFalse($guardCalled, 'Guard should not be called with incompatible parameter');
        $this->assertFalse($factoryCalled, 'Factory should not be called when parameter incompatible');
    }

    public function testNoSpawningOccursWithIncompatibleParameter(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $spawnedRegions = [];
        
        $guard = function(TypedTrigger $t): bool {
            return true;
        };
        
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
        
        // Trigger multiple times with incompatible types
        $region->trigger(new stdClass());
        $region->trigger(new stdClass());
        $region->trigger(new stdClass());
        
        // No regions should have been spawned
        $this->assertCount(0, $spawnedRegions, 'No regions should spawn with incompatible parameters');
    }
}
