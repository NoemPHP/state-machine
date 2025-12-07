<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionSpawnRecord;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionSpawnRecord stores parent state name
 */
#[Group('loader')]
#[Group('spawn-record')]
class SpawnRecordParentStateTest extends TestCase
{
    public function testStoresParentStateName(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('stateA', 'stateB')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();

        $record = new RegionSpawnRecord($parentRegion, 'stateA', $factory, $guard);

        $this->assertSame('stateA', $record->parentStateName);
    }

    public function testParentStateNameIsReadonly(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('stateX')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();

        $record = new RegionSpawnRecord($parentRegion, 'stateX', $factory, $guard);

        $this->assertIsString($record->parentStateName);
        $this->assertSame('stateX', $record->parentStateName);
    }

    public function testDifferentStatesCreateDifferentRecords(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('stateA', 'stateB', 'stateC')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();

        $record1 = new RegionSpawnRecord($parentRegion, 'stateA', $factory, $guard);
        $record2 = new RegionSpawnRecord($parentRegion, 'stateB', $factory, $guard);
        $record3 = new RegionSpawnRecord($parentRegion, 'stateC', $factory, $guard);

        $this->assertSame('stateA', $record1->parentStateName);
        $this->assertSame('stateB', $record2->parentStateName);
        $this->assertSame('stateC', $record3->parentStateName);
    }
}
