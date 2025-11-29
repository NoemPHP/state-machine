<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionSpawnRecord;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionSpawnRegistry addRecord stores spawn record
 */
#[Group('loader')]
#[Group('spawn-registry')]
class SpawnRegistryAddRecordTest extends TestCase
{
    public function testAddRecordStoresSpawnRecord(): void
    {
        $registry = new RegionSpawnRegistry();
        
        // Create a sample region and spawn record
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $registry->addRecord($record);
        
        $this->assertCount(1, $registry->records);
        $this->assertSame($record, $registry->records[0]);
    }

    public function testMultipleRecordsCanBeAdded(): void
    {
        $registry = new RegionSpawnRegistry();
        
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record1 = new RegionSpawnRecord($parentRegion, 'stateA', $factory, $guard);
        $record2 = new RegionSpawnRecord($parentRegion, 'stateB', $factory, $guard);
        $record3 = new RegionSpawnRecord($parentRegion, 'stateC', $factory, $guard);
        
        $registry->addRecord($record1);
        $registry->addRecord($record2);
        $registry->addRecord($record3);
        
        $this->assertCount(3, $registry->records);
        $this->assertSame($record1, $registry->records[0]);
        $this->assertSame($record2, $registry->records[1]);
        $this->assertSame($record3, $registry->records[2]);
    }

    public function testRecordsAreStoredInOrder(): void
    {
        $registry = new RegionSpawnRegistry();
        
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $records = [];
        for ($i = 0; $i < 5; $i++) {
            $record = new RegionSpawnRecord($parentRegion, "state$i", $factory, $guard);
            $records[] = $record;
            $registry->addRecord($record);
        }
        
        $this->assertCount(5, $registry->records);
        
        // Verify order is maintained
        for ($i = 0; $i < 5; $i++) {
            $this->assertSame($records[$i], $registry->records[$i]);
        }
    }
}
