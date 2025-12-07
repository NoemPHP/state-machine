<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Connections are registered with the ConnectedRegions chain during build
 */
#[Group('region-builder')]
#[Group('connection-management')]
class ConnectionRegistrationTest extends TestCase
{
    public function testConnectionsAreRegisteredDuringBuild(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        $mainBuilder->connect($childRegion);

        // Verify ConnectedRegions chain is available in ChainMail
        $connectedRegionsChain = $mainBuilder->chainMail->get(ConnectedRegions::class);
        $this->assertInstanceOf(ConnectedRegions::class, $connectedRegionsChain);

        $parentRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $parentRegion);
    }

    public function testMultipleConnectionsAreRegistered(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $child1 = (new RegionBuilder())->setStates('child1')->build();
        $child2 = (new RegionBuilder())->setStates('child2')->build();
        $child3 = (new RegionBuilder())->setStates('child3')->build();

        $mainBuilder->connect($child1)
                    ->connect($child2)
                    ->connect($child3);

        $parentRegion = $mainBuilder->build();

        $this->assertInstanceOf(Region::class, $parentRegion);
    }

    public function testConnectionsAreRegisteredWithConnectedRegionsChain(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        // Get ConnectedRegions chain before build
        $connectedRegionsChain = $mainBuilder->chainMail->get(ConnectedRegions::class);

        $mainBuilder->connect($childRegion);
        $parentRegion = $mainBuilder->build();

        // Connections should be registered with the chain
        $this->assertInstanceOf(ConnectedRegions::class, $connectedRegionsChain);
        $this->assertInstanceOf(Region::class, $parentRegion);
    }
}
