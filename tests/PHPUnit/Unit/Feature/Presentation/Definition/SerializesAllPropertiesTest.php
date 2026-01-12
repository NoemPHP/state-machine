<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Presentation\Definition;

use Noem\State\Feature\Presentation\RegionPresentation;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criteria: RegionPresentation.jsonSerialize() includes key, label, intent, and metadata
 * Intent: Preserves complete presentation contract during serialization for external introspection
 * Criticality: contract
 */
final class SerializesAllPropertiesTest extends TestCase
{
    public function testJsonSerializeIncludesAllProperties(): void
    {
        $metadata = ['format' => 'currency', 'precision' => 2];

        $presentation = new RegionPresentation(
            key: 'price',
            label: 'Price',
            intent: 'Product price',
            metadata: $metadata
        );

        $serialized = $presentation->jsonSerialize();

        $this->assertArrayHasKey('key', $serialized);
        $this->assertArrayHasKey('label', $serialized);
        $this->assertArrayHasKey('intent', $serialized);
        $this->assertArrayHasKey('metadata', $serialized);

        $this->assertSame('price', $serialized['key']);
        $this->assertSame('Price', $serialized['label']);
        $this->assertSame('Product price', $serialized['intent']);
        $this->assertSame($metadata, $serialized['metadata']);
    }

    public function testJsonSerializeWithNullMetadata(): void
    {
        $presentation = new RegionPresentation(
            key: 'key',
            label: 'Label',
            intent: 'Intent'
        );

        $serialized = $presentation->jsonSerialize();

        $this->assertArrayHasKey('metadata', $serialized);
        $this->assertNull($serialized['metadata']);
    }
}
