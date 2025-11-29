<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Connection;
use Noem\State\Feature\Loader\RegionSpawnRecord;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionSpawnRecord uses default connection flags when not provided
 */
#[Group('loader')]
#[Group('spawn-record')]
class SpawnRecordDefaultFlagsTest extends TestCase
{
    public function testUsesDefaultFlagsWhenNotProvided(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $expectedFlags = Connection::DYNAMIC | Connection::RECEIVE_EVENTS | Connection::RECEIVE_ACTIONS;
        $this->assertSame($expectedFlags, $record->connectionFlags);
    }

    public function testDefaultFlagsIncludeDynamic(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertTrue(($record->connectionFlags & Connection::DYNAMIC) === Connection::DYNAMIC);
    }

    public function testDefaultFlagsIncludeReceiveEvents(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertTrue(($record->connectionFlags & Connection::RECEIVE_EVENTS) === Connection::RECEIVE_EVENTS);
    }

    public function testDefaultFlagsIncludeReceiveActions(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertTrue(($record->connectionFlags & Connection::RECEIVE_ACTIONS) === Connection::RECEIVE_ACTIONS);
    }

    public function testDefaultFlagsDoNotIncludeReceiveMeta(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertFalse(($record->connectionFlags & Connection::RECEIVE_META) === Connection::RECEIVE_META);
    }
}
