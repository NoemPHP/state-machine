<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\ReflectiveMessageSerialization;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that ReflectiveMessageSerialization trait provides automatic fromData() implementation
 *
 * Spec: json-serialization / ReflectiveMessageSerialization trait provides automatic fromData() implementation
 * Intent: Enables automatic deserialization via constructor parameter reflection for simple messages
 * Criticality: detail
 */

#[Group('feature')]
#[Group('message')]
class ReflectionFromDataTest extends TestCase
{
    public function testReflectiveMessageSerializationTraitProvidesAutomaticFromDataImplementation(): void
    {
        $messageClass = new class ('original', 99) extends Message {
            use ReflectiveMessageSerialization;

            public function __construct(
                public readonly string $content,
                public readonly int $count,
                ?string $correlationId = null
            ) {
                parent::__construct($correlationId);
            }
        };

        $jsonData = [
            'type' => $messageClass::class,
            'correlationId' => 'test-correlation-id',
            'data' => ['content' => 'reconstructed', 'count' => 123]
        ];

        $reconstructed = $messageClass::fromJson($jsonData);

        $this->assertSame('reconstructed', $reconstructed->content);
        $this->assertSame(123, $reconstructed->count);
        $this->assertSame('test-correlation-id', $reconstructed->correlationId());
    }
}
