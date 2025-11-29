<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Closure;
use Noem\State\Feature\Loader\RegionSpawnRecord;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: RegionSpawnRecord stores guard closure
 */
#[Group('loader')]
#[Group('spawn-record')]
class SpawnRecordGuardTest extends TestCase
{
    public function testStoresGuardClosure(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertInstanceOf(Closure::class, $record->guard);
        $this->assertSame($guard, $record->guard);
    }

    public function testGuardClosureCanBeInvoked(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $invoked = false;
        $guard = function(object $t) use (&$invoked): bool {
            $invoked = true;
            return true;
        };
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertFalse($invoked);
        
        $result = ($record->guard)(new stdClass());
        
        $this->assertTrue($invoked);
        $this->assertTrue($result);
    }

    public function testGuardReturnsCorrectValue(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $guardTrue = fn(object $t): bool => true;
        $guardFalse = fn(object $t): bool => false;
        
        $recordTrue = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guardTrue);
        $recordFalse = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guardFalse);
        
        $this->assertTrue(($recordTrue->guard)(new stdClass()));
        $this->assertFalse(($recordFalse->guard)(new stdClass()));
    }

    public function testGuardReceivesTriggerParameter(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $receivedTrigger = null;
        $guard = function(object $t) use (&$receivedTrigger): bool {
            $receivedTrigger = $t;
            return true;
        };
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $trigger = new stdClass();
        $trigger->testProperty = 'testValue';
        
        ($record->guard)($trigger);
        
        $this->assertSame($trigger, $receivedTrigger);
        $this->assertSame('testValue', $receivedTrigger->testProperty);
    }
}
