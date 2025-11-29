<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Connection;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRecord;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: regionSpawnStep creates RegionSpawnRecord with provided parameters
 */
#[Group('loader')]
#[Group('spawn-step-execution')]
class SpawnRecordCreationTest extends TestCase
{
    public function testRegionSpawnStepCreatesRecordWithProvidedParameters(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $stateName = 'spawningState';
        $guard = fn(object $t): bool => true;
        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();
        $flags = Connection::DYNAMIC | Connection::RECEIVE_EVENTS;
        
        $spawnStep = RegionLoader::regionSpawnStep(
            $stateName,
            $regionFactory,
            $guard,
            $flags
        );
        
        $builder->setStates('parent');
        $builder->addBuildStep($spawnStep);
        
        $region = $builder->build();
        
        // Get the spawn registry and verify the record was created
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        
        $this->assertCount(1, $registry->records);
        
        $record = $registry->records[0];
        $this->assertInstanceOf(RegionSpawnRecord::class, $record);
        $this->assertSame($region, $record->parentRegion);
        $this->assertSame($stateName, $record->parentStateName);
        $this->assertSame($guard, $record->guard);
        $this->assertSame($flags, $record->connectionFlags);
    }

    public function testRegionSpawnStepUsesDefaultFlagsWhenNotProvided(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $stateName = 'spawningState';
        $guard = fn(object $t): bool => true;
        $regionFactory = fn(): Region => $builder->newInstance()
            ->setStates('child')
            ->build();
        
        $spawnStep = RegionLoader::regionSpawnStep(
            $stateName,
            $regionFactory,
            $guard
        );
        
        $builder->setStates('parent');
        $builder->addBuildStep($spawnStep);
        
        $region = $builder->build();
        
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        $record = $registry->records[0];
        
        $expectedFlags = Connection::DYNAMIC 
            | Connection::RECEIVE_EVENTS 
            | Connection::RECEIVE_ACTIONS;
        
        $this->assertSame($expectedFlags, $record->connectionFlags);
    }
}
