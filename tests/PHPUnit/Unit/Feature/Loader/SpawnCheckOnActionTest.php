<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Connection;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: RegionLoader checks spawn registry on every action dispatch
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnCheckOnActionTest extends TestCase
{
    public function testChecksSpawnRegistryOnEveryActionDispatch(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $guardCallCount = 0;
        $guard = function (object $t) use (&$guardCallCount): bool {
            $guardCallCount++;
            return false; // Don't actually spawn
        };

        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();

        // Create a region with a spawn configuration
        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $region = $builder->build();

        // Initially, guard should not have been called
        $this->assertSame(0, $guardCallCount, 'Guard should not be called before any actions');

        // Trigger an action
        $region->trigger(new stdClass());

        // Guard should have been checked once
        $this->assertSame(1, $guardCallCount, 'Guard should be checked on first action');

        // Trigger more actions
        $region->trigger(new stdClass());
        $region->trigger(new stdClass());

        // Guard should have been checked for each action
        $this->assertSame(3, $guardCallCount, 'Guard should be checked on every action');
    }

    public function testChecksAllSpawnRecordsOnEachAction(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $guard1CallCount = 0;
        $guard2CallCount = 0;

        $guard1 = function (object $t) use (&$guard1CallCount): bool {
            $guard1CallCount++;
            return false;
        };

        $guard2 = function (object $t) use (&$guard2CallCount): bool {
            $guard2CallCount++;
            return false;
        };

        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();

        // Create a region with multiple spawn configurations
        $builder->setStates('state1', 'state2');
        $builder->addBuildStep(RegionLoader::regionSpawnStep('state1', $regionFactory, $guard1));
        $builder->addBuildStep(RegionLoader::regionSpawnStep('state2', $regionFactory, $guard2));

        $region = $builder->build();

        // Trigger an action
        $region->trigger(new stdClass());

        // Both guards should have been checked
        $this->assertSame(1, $guard1CallCount, 'First guard should be checked');
        $this->assertSame(1, $guard2CallCount, 'Second guard should be checked');
    }
}
