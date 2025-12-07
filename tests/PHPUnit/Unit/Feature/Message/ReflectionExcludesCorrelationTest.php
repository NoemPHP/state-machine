<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\ReflectiveMessageSerialization;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that ReflectiveMessageSerialization excludes correlationId from data payload
 *
 * Spec: json-serialization / ReflectiveMessageSerialization excludes correlationId from data payload
 * Intent: Prevents duplicate correlation ID in serialized output since already included at top level
 * Criticality: detail
 */

#[Group('feature')]
#[Group('message')]
class ReflectionExcludesCorrelationTest extends TestCase
{
    public function testReflectiveMessageSerializationExcludesCorrelationIdFromDataPayload(): void
    {
        $message = new class ('test', 42) extends Message {
            use ReflectiveMessageSerialization;

            public function __construct(
                public readonly string $content,
                public readonly int $count,
                ?string $correlationId = null
            ) {
                parent::__construct($correlationId);
            }
        };

        $serialized = $message->jsonSerialize();

        // Verify correlationId is at top level
        $this->assertArrayHasKey('correlationId', $serialized);

        // Verify correlationId is NOT in data payload
        $this->assertArrayHasKey('data', $serialized);
        $this->assertIsArray($serialized['data']);
        $this->assertArrayNotHasKey('correlationId', $serialized['data'], 'correlationId should be excluded from data payload');
    }
}
