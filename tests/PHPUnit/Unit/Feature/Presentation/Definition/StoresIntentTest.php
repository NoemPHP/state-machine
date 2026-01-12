<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: RegionPresentation stores intent as readonly string property
 * Intent: Describes semantic meaning and purpose for context-aware rendering decisions
 * Criticality: contract
 */
final class StoresIntentTest extends TestCase
{
    public function testRegionPresentationStoresIntent(): void
    {
        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Label',
            intent: 'Describes the semantic purpose of this value for AI agents'
        );

        $this->assertSame('Describes the semantic purpose of this value for AI agents', $presentation->intent);
    }
}
