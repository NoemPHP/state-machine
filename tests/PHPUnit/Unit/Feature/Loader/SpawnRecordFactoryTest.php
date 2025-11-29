<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Closure;
use Noem\State\Feature\Loader\RegionSpawnRecord;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionSpawnRecord stores region factory closure
 */
#[Group('loader')]
#[Group('spawn-record')]
class SpawnRecordFactoryTest extends TestCase
{
    public function testStoresRegionFactoryClosure(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertInstanceOf(Closure::class, $record->regionFactory);
        $this->assertSame($factory, $record->regionFactory);
    }

    public function testFactoryClosureCanBeInvoked(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $invoked = false;
        
        $factory = function() use (&$invoked): Region {
            $invoked = true;
            return (new RegionBuilder())->setStates('child')->build();
        };
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertFalse($invoked);
        
        $spawnedRegion = ($record->regionFactory)();
        
        $this->assertTrue($invoked);
        $this->assertInstanceOf(Region::class, $spawnedRegion);
    }

    public function testFactoryReturnsCorrectRegion(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('customChild')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $spawnedRegion = ($record->regionFactory)();
        
        $this->assertTrue($spawnedRegion->isInState('customChild'));
    }
}
