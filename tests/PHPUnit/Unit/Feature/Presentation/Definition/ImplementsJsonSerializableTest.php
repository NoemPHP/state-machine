<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use JsonSerializable;
use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: RegionPresentation implements JsonSerializable interface
 * Intent: Enables JSON serialization for discovery responses and external agent consumption
 * Criticality: contract
 */
final class ImplementsJsonSerializableTest extends TestCase
{
    public function testRegionPresentationImplementsJsonSerializable(): void
    {
        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Label',
            intent: 'Intent'
        );

        $this->assertInstanceOf(JsonSerializable::class, $presentation);
    }

    public function testJsonSerializeMethodExists(): void
    {
        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Label',
            intent: 'Intent'
        );

        $this->assertTrue(method_exists($presentation, 'jsonSerialize'));
    }
}
