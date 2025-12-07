<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.jsonSerialize() includes correlation ID in serialized output
 *
 * Spec: json-serialization / Message.jsonSerialize() includes correlation ID in serialized output
 * Intent: Preserves correlation context during JSON round-trip for distributed messaging scenarios
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class CorrelationInSerializationTest extends TestCase
{
    public function testJsonSerializeIncludesCorrelationIdInSerializedOutput(): void
    {
        $message = new class extends Message {
            public function __construct(?string $correlationId = null)
            {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return [
                    'correlationId' => $this->correlationId,
                    'type' => 'Test',
                    'data' => ['foo' => 'bar']
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($correlationId);
            }
        };

        $serialized = $message->jsonSerialize();
        $expectedCorrelationId = $message->correlationId();

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('correlationId', $serialized, 'Serialized output must include correlationId');
        $this->assertSame($expectedCorrelationId, $serialized['correlationId'], 'Serialized correlationId must match message correlationId');
    }
}
