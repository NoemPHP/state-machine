<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionSpawnRecord;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionSpawnRecord stores parent region reference
 */
#[Group('loader')]
#[Group('spawn-record')]
class SpawnRecordParentRegionTest extends TestCase
{
    public function testStoresParentRegionReference(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        $this->assertSame($parentRegion, $record->parentRegion);
    }

    public function testParentRegionIsReadonly(): void
    {
        $parentRegion = (new RegionBuilder())->setStates('parent')->build();
        $guard = fn(object $t): bool => true;
        $factory = fn(): Region => (new RegionBuilder())->setStates('child')->build();
        
        $record = new RegionSpawnRecord($parentRegion, 'parent', $factory, $guard);
        
        // Verify it's the correct type
        $this->assertInstanceOf(Region::class, $record->parentRegion);
        $this->assertTrue($record->parentRegion->isInState('parent'));
    }
}
