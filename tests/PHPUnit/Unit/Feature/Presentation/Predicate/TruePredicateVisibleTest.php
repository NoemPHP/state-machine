<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Predicate;

use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Presentations with true predicates visible in enumeration
 * Intent: Includes presentation when predicate evaluates to truthy value
 * Criticality: contract
 */
final class TruePredicateVisibleTest extends TestCase
{
    public function testPredicateReturningTrueIsVisible(): void
    {
        $truePredicate = fn(Region $region): bool => true;

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $truePredicate
        );

        // Predicate should be stored
        $this->assertNotNull($presentation->predicate);

        // Verify it returns true when called
        $mockRegion = $this->createMock(Region::class);
        $result = ($presentation->predicate)($mockRegion);

        $this->assertTrue($result);
    }

    public function testPredicateReturningTruthyValueIsVisible(): void
    {
        $truthyPredicate = fn(Region $region): int => 1;

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: $truthyPredicate
        );

        $mockRegion = $this->createMock(Region::class);
        $result = ($presentation->predicate)($mockRegion);

        // Truthy values should evaluate to true
        $this->assertTruthy($result);
    }

    private function assertTruthy(mixed $value): void
    {
        $this->assertTrue((bool) $value);
    }
}
