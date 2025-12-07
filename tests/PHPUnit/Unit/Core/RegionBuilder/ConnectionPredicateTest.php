<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: connect accepts optional predicate for conditional connections
 */
#[Group('region-builder')]
#[Group('connection-management')]
class ConnectionPredicateTest extends TestCase
{
    public function testConnectAcceptsOptionalPredicate(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        $predicate = fn(): bool => true;

        $result = $mainBuilder->connect($childRegion, predicate: $predicate);

        $this->assertSame($mainBuilder, $result, 'connect should return builder for chaining');

        $parentRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $parentRegion);
    }

    public function testPredicateCanBeUsedForConditionalConnection(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        $enabledPredicate = fn(): bool => true;
        $disabledPredicate = fn(): bool => false;

        $mainBuilder->connect($childRegion, predicate: $enabledPredicate)
                    ->connect($childRegion, predicate: $disabledPredicate);

        $parentRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $parentRegion);
    }

    public function testConnectWithFlagsAndPredicate(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        $flags = 1 << 3;
        $predicate = fn(): bool => true;

        $mainBuilder->connect($childRegion, $flags, $predicate);

        $parentRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $parentRegion);
    }

    public function testPredicateIsOptional(): void
    {
        $mainBuilder = new RegionBuilder();
        $mainBuilder->setStates('parent');

        $childRegion = (new RegionBuilder())->setStates('child')->build();

        // Connect without predicate should work
        $mainBuilder->connect($childRegion, flags: 0, predicate: null);

        $parentRegion = $mainBuilder->build();
        $this->assertInstanceOf(Region::class, $parentRegion);
    }
}
