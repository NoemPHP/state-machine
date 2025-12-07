<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Connection;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: regionSpawnStep adds spawn record to registry
 */
#[Group('loader')]
#[Group('spawn-step-execution')]
class SpawnRecordRegistrationTest extends TestCase
{
    public function testRegionSpawnStepAddsSpawnRecordToRegistry(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $stateName = 'spawningState';
        $guard = fn(object $t): bool => true;
        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();

        // Create spawn step
        $spawnStep = RegionLoader::regionSpawnStep(
            $stateName,
            $regionFactory,
            $guard
        );

        $builder->setStates('parent');
        $builder->addBuildStep($spawnStep);

        // Build the region (features are invoked during build, including RegionSpawnRegistry registration)
        $region = $builder->build();

        // Get registry after building
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);

        // Verify the record was added to the registry
        $this->assertCount(1, $registry->records, 'Registry should contain one record after build');

        $record = $registry->records[0];
        $this->assertSame($region, $record->parentRegion);
    }

    public function testMultipleSpawnStepsAddMultipleRecords(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());

        $guard = fn(object $t): bool => true;
        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();

        // Add multiple spawn steps
        $builder->setStates('state1', 'state2', 'state3');
        $builder->addBuildStep(RegionLoader::regionSpawnStep('state1', $regionFactory, $guard));
        $builder->addBuildStep(RegionLoader::regionSpawnStep('state2', $regionFactory, $guard));
        $builder->addBuildStep(RegionLoader::regionSpawnStep('state3', $regionFactory, $guard));

        $region = $builder->build();

        // Verify all records were added
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $this->assertCount(3, $registry->records, 'Registry should contain three records');
    }
}
