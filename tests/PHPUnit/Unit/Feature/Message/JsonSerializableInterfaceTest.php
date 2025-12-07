<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message class implements JsonSerializable interface
 *
 * Spec: json-serialization / Message class implements JsonSerializable interface
 * Intent: Ensures all messages can be serialized to JSON for persistence, transmission, and logging
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class JsonSerializableInterfaceTest extends TestCase
{
    public function testMessageClassImplementsJsonSerializableInterface(): void
    {
        $message = new class extends Message {
            public function __construct(?string $correlationId = null)
            {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return ['correlationId' => $this->correlationId, 'type' => 'Test', 'data' => []];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($correlationId);
            }
        };

        $this->assertInstanceOf(\JsonSerializable::class, $message, 'Message should implement JsonSerializable interface');
    }
}
