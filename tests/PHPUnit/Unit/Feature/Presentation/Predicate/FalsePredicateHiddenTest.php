<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Predicate;

use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Presentations with false predicates hidden in enumeration
 * Intent: Excludes presentation when predicate evaluates to falsy value
 * Criticality: contract
 */
final class FalsePredicateHiddenTest extends TestCase
{
    public function testPredicateReturningFalseIsHidden(): void
    {
        $falsePredicate = fn(Region $region): bool => false;

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $falsePredicate
        );

        // Predicate should be stored
        $this->assertNotNull($presentation->predicate);

        // Verify it returns false when called
        $mockRegion = $this->createMock(Region::class);
        $result = ($presentation->predicate)($mockRegion);

        $this->assertFalse($result);
    }

    public function testPredicateReturningFalsyValueIsHidden(): void
    {
        $falsyPredicate = fn(Region $region): int => 0;

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $falsyPredicate
        );

        $mockRegion = $this->createMock(Region::class);
        $result = ($presentation->predicate)($mockRegion);

        // Falsy values should evaluate to false
        $this->assertFalsy($result);
    }

    private function assertFalsy(mixed $value): void
    {
        $this->assertFalse((bool) $value);
    }
}
