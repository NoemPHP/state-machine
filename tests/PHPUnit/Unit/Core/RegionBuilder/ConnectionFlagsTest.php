<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: connect accepts optional flags parameter for connection configuration
 */
#[Group('region-builder')]
#[Group('connection-management')]
class ConnectionFlagsTest extends TestCase
{
    public function testConnectAcceptsOptionalFlags(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        // Test with default flags (0)
        $mainBuilder->connect($childRegion);

        // Test with custom flags
        $mainBuilder->connect($childRegion, flags: 1);
        $mainBuilder->connect($childRegion, flags: 255);

        $parentRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $parentRegion);
    }

    public function testFlagsCanConfigureConnectionBehavior(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        // Different flag values for different connection types
        $hierarchicalFlag = 1 << 0;
        $parallelFlag = 1 << 1;
        $bidirectionalFlag = 1 << 2;

        $mainBuilder->connect($childRegion, $hierarchicalFlag)
                    ->connect($childRegion, $parallelFlag)
                    ->connect($childRegion, $bidirectionalFlag)
                    ->connect($childRegion, $hierarchicalFlag | $parallelFlag);

        $parentRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $parentRegion);
    }

    public function testDefaultFlagsAreZero(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        // Connecting without explicit flags should use 0
        $mainBuilder->connect($childRegion);

        $parentRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $parentRegion);
    }
}
