<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: regionSpawnStep returns built region
 */
#[Group('loader')]
#[Group('spawn-step-execution')]
class SpawnStepReturnTest extends TestCase
{
    public function testRegionSpawnStepReturnsBuiltRegion(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $stateName = 'spawningState';
        $guard = fn(object $t): bool => true;
        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();

        // Create and add spawn step
        $spawnStep = RegionLoader::regionSpawnStep(
            $stateName,
            $regionFactory,
            $guard
        );

        $builder->setStates('parent');
        $builder->addBuildStep($spawnStep);

        // Build the region - the spawn step should return the built region
        $region = $builder->build();

        $this->assertInstanceOf(Region::class, $region);
        $this->assertSame('parent', $region->currentState());
    }

    public function testSpawnStepDoesNotAffectRegionBuilding(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $guard = fn(object $t): bool => true;
        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();

        // Build with spawn step
        $builder->setStates('state1', 'state2');
        $builder->markInitial('state1');
        $builder->addBuildStep(RegionLoader::regionSpawnStep('state1', $regionFactory, $guard));

        $region = $builder->build();

        // Verify the region is built correctly and the spawn step didn't interfere
        $this->assertInstanceOf(Region::class, $region);
        $this->assertSame('state1', $region->currentState());
        $this->assertFalse($region->isFinal());
    }
}
