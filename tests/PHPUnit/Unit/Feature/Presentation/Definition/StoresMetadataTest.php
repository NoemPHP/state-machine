<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: RegionPresentation stores optional metadata array as readonly property
 * Intent: Provides extensible format hints (precision, unit, format) for rich rendering
 * Criticality: contract
 */
final class StoresMetadataTest extends TestCase
{
    public function testRegionPresentationStoresMetadata(): void
    {
        $metadata = ['precision' => 2, 'unit' => 'USD', 'format' => 'currency'];

        $presentation = new RegionPresentation(
            key: 'totalPrice',
            label: 'Total Price',
            intent: 'Order total with tax',
            metadata: $metadata
        );

        $this->assertSame($metadata, $presentation->metadata);
    }

    public function testMetadataDefaultsToNull(): void
    {
        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Label',
            intent: 'Intent'
        );

        $this->assertNull($presentation->metadata);
    }
}
