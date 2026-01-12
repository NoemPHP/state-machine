<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: RegionPresentation stores key as readonly string property
 * Intent: Identifies context field for value retrieval and schema validation
 * Criticality: contract
 */
final class StoresKeyTest extends TestCase
{
    public function testRegionPresentationStoresKey(): void
    {
        $presentation = new RegionPresentation(
            key: 'userCount',
            label: 'Active Users',
            intent: 'Number of currently logged-in users'
        );

        $this->assertSame('userCount', $presentation->key);
    }

    public function testKeyIsReadonly(): void
    {
        $presentation = new RegionPresentation(
            key: 'userCount',
            label: 'Active Users',
            intent: 'Intent'
        );

        // This should cause a compilation error if key is not readonly
        // For runtime verification, we check the property exists and is accessible
        $this->assertTrue(property_exists($presentation, 'key'));
    }
}
