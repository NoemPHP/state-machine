<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.fromJson() uses type field to instantiate correct subclass
 *
 * Spec: json-serialization / Message.fromJson() uses type field to instantiate correct subclass
 * Intent: Restores original message type during deserialization for polymorphic message handling
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class TypeBasedReconstructionTest extends TestCase
{
    public function testFromJsonUsesTypeFieldToInstantiateCorrectSubclass(): void
    {
        $messageClass = new class ('test content') extends Message {
            public function __construct(
                public readonly string $content = 'default',
                ?string $correlationId = null
            ) {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return [
                    'correlationId' => $this->correlationId,
                    'type' => static::class,
                    'data' => ['content' => $this->content]
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($data['content'] ?? 'default', $correlationId);
            }
        };

        $serialized = $messageClass->jsonSerialize();

        // Reconstruct using type field from serialized data
        $reconstructed = Message::fromJson($serialized);

        $this->assertInstanceOf($messageClass::class, $reconstructed, 'fromJson should use type field to instantiate correct subclass');
        $this->assertSame($messageClass->content, $reconstructed->content);
    }
}
