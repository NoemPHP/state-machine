<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.correlationId() returns readonly UUID string
 *
 * Spec: uuid-correlation / Message.correlationId() returns readonly UUID string
 * Intent: Provides immutable access to correlation identifier for matching request-response pairs
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class CorrelationIdAccessTest extends TestCase
{
    public function testCorrelationIdReturnsReadonlyUuidString(): void
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

        $id1 = $message->correlationId();
        $id2 = $message->correlationId();

        // Should return the same ID on multiple calls (readonly)
        $this->assertSame($id1, $id2, 'correlationId() should return the same value on multiple calls');

        // Should be a valid UUID string
        $this->assertIsString($id1);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $id1);
    }
}
