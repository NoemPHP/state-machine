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
 * Acceptance Criterion: RegionSpawnRegistry records property is private(set)
 */
#[Group('loader')]
#[Group('spawn-registry')]
class SpawnRegistryAccessTest extends TestCase
{
    public function testRecordsPropertyIsReadable(): void
    {
        $registry = new RegionSpawnRegistry();

        // Should be able to read the records property
        $records = $registry->records;

        $this->assertIsArray($records);
        $this->assertEmpty($records);
    }

    public function testRecordsPropertyCanBeAccessedAfterAddingRecords(): void
    {
        $registry = new RegionSpawnRegistry();

        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();

        $record1 = new RegionSpawnRecord($parentRegion, 'stateA', $factory, $guard);
        $record2 = new RegionSpawnRecord($parentRegion, 'stateB', $factory, $guard);

        $registry->addRecord($record1);
        $registry->addRecord($record2);

        // Should be able to read the updated records
        $records = $registry->records;

        $this->assertCount(2, $records);
        $this->assertSame($record1, $records[0]);
        $this->assertSame($record2, $records[1]);
    }

    public function testRecordsCanBeIteratedOver(): void
    {
        $registry = new RegionSpawnRegistry();

        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();

        $registry->addRecord(new RegionSpawnRecord($parentRegion, 'stateA', $factory, $guard));
        $registry->addRecord(new RegionSpawnRecord($parentRegion, 'stateB', $factory, $guard));
        $registry->addRecord(new RegionSpawnRecord($parentRegion, 'stateC', $factory, $guard));

        // Should be able to iterate over records
        $count = 0;
        foreach ($registry->records as $record) {
            $this->assertInstanceOf(RegionSpawnRecord::class, $record);
            $count++;
        }

        $this->assertSame(3, $count);
    }
}
