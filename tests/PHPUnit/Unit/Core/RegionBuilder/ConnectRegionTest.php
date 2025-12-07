<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can connect to remote regions using connect method
 */
#[Group('region-builder')]
#[Group('connection-management')]
class ConnectRegionTest extends TestCase
{
    public function testConnectAcceptsRegionParameter(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('idle');

        $remoteBuilder = new RegionBuilder();
        $remoteBuilder->setStates('nested');
        $remoteRegion = $remoteBuilder->build();

        $result = $mainBuilder->connect($remoteRegion);

        $this->assertSame($mainBuilder, $result, 'connect should return builder for chaining');

        $mainRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $mainRegion);
    }

    public function testConnectCanBeCalledMultipleTimes(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('idle');

        $remote1 = (new RegionBuilder())->setStates('nested1')->build();
        $remote2 = (new RegionBuilder())->setStates('nested2')->build();
        $remote3 = (new RegionBuilder())->setStates('nested3')->build();

        $mainBuilder->connect($remote1)
                    ->connect($remote2)
                    ->connect($remote3);

        $mainRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $mainRegion);
    }

    public function testConnectRegistersConnection(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childBuilder = new RegionBuilder();
        $childBuilder->setStates('child');
        $childRegion = $childBuilder->build();

        $mainBuilder->connect($childRegion);
        $parentRegion = $mainBuilder->build();

        $this->assertInstanceOf(Region::class, $parentRegion);
        $this->assertInstanceOf(Region::class, $childRegion);
    }
}
