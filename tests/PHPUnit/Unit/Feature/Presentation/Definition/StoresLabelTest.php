<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: RegionPresentation stores label as readonly string property
 * Intent: Provides human-readable display name for UI rendering and documentation
 * Criticality: contract
 */
final class StoresLabelTest extends TestCase
{
    public function testRegionPresentationStoresLabel(): void
    {
        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Human Readable Label',
            intent: 'Intent'
        );

        $this->assertSame('Human Readable Label', $presentation->label);
    }
}
