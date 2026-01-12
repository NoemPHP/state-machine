<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Region;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: RegionPresentation stores optional predicate callable as readonly property
 * Intent: Associates conditional logic for runtime filtering, enabling context-aware exposure
 * Criticality: contract
 */
final class StoresPredicateTest extends TestCase
{
    public function testRegionPresentationStoresPredicate(): void
    {
        $predicate = fn(Region $region): bool => $region->getCurrentState() === 'active';

        $presentation = new RegionPresentation(
            key: 'activeStatus',
            label: 'Status',
            intent: 'Current status',
            predicate: $predicate
        );

        $this->assertSame($predicate, $presentation->predicate);
    }

    public function testPredicateDefaultsToNull(): void
    {
        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Label',
            intent: 'Intent'
        );

        $this->assertNull($presentation->predicate);
    }
}
