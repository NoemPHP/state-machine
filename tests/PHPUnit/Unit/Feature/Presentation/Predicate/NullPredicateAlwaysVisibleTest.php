<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Predicate;

use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: Presentations without predicates always visible in enumeration
 * Intent: Treats null predicate as unconditionally visible, simplifying common case
 * Criticality: contract
 */
final class NullPredicateAlwaysVisibleTest extends TestCase
{
    public function testPresentationWithNullPredicateIsAlwaysVisible(): void
    {
        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent',
            predicate: null
        );

        // Null predicate should be stored
        $this->assertNull($presentation->predicate);
    }

    public function testOmittedPredicateDefaultsToNull(): void
    {
        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent'
        );

        // Predicate should be null when omitted
        $this->assertNull($presentation->predicate);
    }
}
