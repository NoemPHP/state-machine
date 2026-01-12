<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Registry;

use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: PresentationRegistry.get() returns RegionPresentation when key exists
 * Intent: Retrieves registered presentation for value retrieval and metadata access
 * Criticality: contract
 */
final class GetReturnsPresentationTest extends TestCase
{
    public function testGetReturnsRegisteredPresentation(): void
    {
        $registry = new PresentationRegistry();
        $registry->setSchemas(['key' => ['type' => 'string']]);

        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Label',
            intent: 'Intent'
        );

        $registry->register($presentation);

        $result = $registry->get('key');

        $this->assertInstanceOf(RegionPresentation::class, $result);
        $this->assertSame($presentation, $result);
    }
}
