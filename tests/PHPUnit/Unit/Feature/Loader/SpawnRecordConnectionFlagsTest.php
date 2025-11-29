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
 * Acceptance Criterion: RegionSpawnRecord stores connection flags
 */
#[Group('loader')]
#[Group('spawn-record')]
class SpawnRecordConnectionFlagsTest extends TestCase
{
    public function testStoresConnectionFlags(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $flags = Connection::RECEIVE_ACTIONS | Connection::RECEIVE_EVENTS;
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard, $flags);
        
        $this->assertSame($flags, $record->connectionFlags);
    }

    public function testConnectionFlagsCanBeCustomized(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $customFlags = Connection::RECEIVE_META | Connection::DYNAMIC;
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard, $customFlags);
        
        $this->assertSame($customFlags, $record->connectionFlags);
    }

    public function testDifferentFlagsCanBeCombined(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $flags = Connection::DYNAMIC 
            | Connection::RECEIVE_EVENTS 
            | Connection::RECEIVE_ACTIONS 
            | Connection::RECEIVE_META;
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard, $flags);
        
        $this->assertSame($flags, $record->connectionFlags);
    }

    public function testConnectionFlagsAreInteger(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard, 42);
        
        $this->assertIsInt($record->connectionFlags);
        $this->assertSame(42, $record->connectionFlags);
    }
}
