<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

class SpecificTrigger
{
    public function __construct(public string $data)
    {
    }
}

class AnotherTrigger
{
    public function __construct(public int $value)
    {
    }
}

/**
 * Acceptance Criterion: SpawnRegion checks parameter compatibility with trigger payload
 */
#[Group('loader')]
#[Group('spawn-execution')]
class SpawnParameterCompatibilityTest extends TestCase
{
    public function testChecksParameterCompatibilityBeforeCallingGuard(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $guardCalled = false;

        // Guard expects SpecificTrigger
        $guard = function (SpecificTrigger $t) use (&$guardCalled): bool {
            $guardCalled = true;
            return true;
        };

        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $region = $builder->build();

        // Trigger with compatible type
        $region->trigger(new SpecificTrigger('test'));
        $this->assertTrue($guardCalled, 'Guard should be called with compatible parameter');

        // Reset
        $guardCalled = false;

        // Trigger with incompatible type - guard should NOT be called
        $region->trigger(new AnotherTrigger(42));
        $this->assertFalse($guardCalled, 'Guard should not be called with incompatible parameter');

        // Trigger with stdClass - guard should NOT be called
        $region->trigger(new stdClass());
        $this->assertFalse($guardCalled, 'Guard should not be called with incompatible stdClass');
    }

    public function testSkipsSpawningWhenParameterIncompatible(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $spawned = false;

        // Guard expects SpecificTrigger and always returns true
        $guard = function (SpecificTrigger $t): bool {
            return true;
        };

        $regionFactory = function () use (&$spawned, $builder): Region {
            $spawned = true;
            return $builder->newInstance()->setStates('child')->build();
        };

        $builder->setStates('parent');
        $builder->addBuildStep(
            RegionLoader::regionSpawnStep('parent', $regionFactory, $guard)
        );

        $region = $builder->build();

        // Trigger with incompatible type - should not spawn
        $region->trigger(new AnotherTrigger(100));
        $this->assertFalse($spawned, 'Should not spawn with incompatible trigger type');

        // Trigger with compatible type - should spawn
        $region->trigger(new SpecificTrigger('spawn me'));
        $this->assertTrue($spawned, 'Should spawn with compatible trigger type');
    }
}
