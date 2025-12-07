<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\ReflectiveMessageSerialization;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that ReflectiveMessageSerialization trait provides automatic jsonSerialize() implementation
 *
 * Spec: json-serialization / ReflectiveMessageSerialization trait provides automatic jsonSerialize() implementation
 * Intent: Offers zero-boilerplate serialization for simple message types via reflection
 * Criticality: detail
 */

#[Group('feature')]
#[Group('message')]
class ReflectionSerializeTest extends TestCase
{
    public function testReflectiveMessageSerializationTraitProvidesAutomaticJsonSerializeImplementation(): void
    {
        $message = new class ('test content', 42) extends Message {
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

        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('correlationId', $serialized);
        $this->assertArrayHasKey('type', $serialized);
        $this->assertArrayHasKey('data', $serialized);
        $this->assertSame($message::class, $serialized['type']);
        $this->assertSame(['content' => 'test content', 'count' => 42], $serialized['data']);
    }
}
