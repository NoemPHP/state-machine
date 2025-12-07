<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.fromJson() reconstructs message from JSON-decoded array
 *
 * Spec: json-serialization / Message.fromJson() reconstructs message from JSON-decoded array
 * Intent: Enables bidirectional JSON serialization for message persistence and transmission
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class FromJsonReconstructionTest extends TestCase
{
    public function testFromJsonReconstructsMessageFromJsonDecodedArray(): void
    {
        $messageClass = new class extends Message {
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

        $originalMessage = new $messageClass('test content');
        $serialized = $originalMessage->jsonSerialize();

        // Reconstruct using fromJson
        $reconstructed = $messageClass::fromJson($serialized, $messageClass::class);

        $this->assertInstanceOf(Message::class, $reconstructed);
        $this->assertSame($originalMessage->content, $reconstructed->content);
        $this->assertSame($originalMessage->correlationId(), $reconstructed->correlationId());
    }
}
