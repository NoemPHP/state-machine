<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\ReflectiveMessageSerialization;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that ReflectiveMessageSerialization.fromData() requires array payload
 *
 * Spec: json-serialization / ReflectiveMessageSerialization.fromData() requires array payload
 * Intent: Enforces consistent data format for reflection-based deserialization with clear error messages
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class ReflectionArrayRequiredTest extends TestCase
{
    public function testReflectiveMessageSerializationFromDataRequiresArrayPayload(): void
    {
        $messageClass = new class ('default', 0) extends Message {
            use ReflectiveMessageSerialization;

            public function __construct(
                public readonly string $content,
                public readonly int $count,
                ?string $correlationId = null
            ) {
                parent::__construct($correlationId);
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ReflectiveMessageSerialization requires array payload');

        // Pass non-array payload via fromJson (should throw exception)
        $jsonData = [
            'type' => $messageClass::class,
            'correlationId' => 'test-id',
            'data' => (object)['content' => 'test']  // Object instead of array
        ];

        $messageClass::fromJson($jsonData);
    }
}
