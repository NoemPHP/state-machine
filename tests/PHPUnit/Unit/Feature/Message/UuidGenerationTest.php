<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message instances generate unique UUID correlation IDs on construction
 *
 * Spec: uuid-correlation / Message instances generate unique UUID correlation IDs on construction
 * Intent: Ensures each message has globally unique identifier for request-response correlation without collisions
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class UuidGenerationTest extends TestCase
{
    public function testMessageInstancesGenerateUniqueUuidCorrelationIds(): void
    {
        $message1 = new class extends Message {
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

        $message2 = new class extends Message {
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

        $id1 = $message1->correlationId();
        $id2 = $message2->correlationId();

        // UUIDs should be non-empty strings
        $this->assertIsString($id1);
        $this->assertNotEmpty($id1);

        // UUIDs should be unique
        $this->assertNotEquals($id1, $id2, 'Each Message should generate a unique correlation ID');

        // UUID format validation (basic check for UUID-like structure)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id1);
    }
}
