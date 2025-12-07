<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.repliesTo() returns true when correlation IDs match
 *
 * Spec: uuid-correlation / Message.repliesTo() returns true when correlation IDs match
 * Intent: Provides explicit correlation checking method for response validation and routing
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class RepliesToMatchingTest extends TestCase
{
    public function testRepliesToReturnsTrueWhenCorrelationIdsMatch(): void
    {
        $requestMessage = new class extends Message {
            public function __construct(?string $correlationId = null)
            {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return ['correlationId' => $this->correlationId, 'type' => 'Request', 'data' => []];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($correlationId);
            }
        };

        $responseClass = get_class(new class extends Message {
            public function __construct(?string $correlationId = null)
            {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return ['correlationId' => $this->correlationId, 'type' => 'Response', 'data' => []];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($correlationId);
            }
        });

        // Create response with same correlation ID
        $responseMessage = $requestMessage->createResponse($responseClass, []);

        // Test repliesTo with matching correlation
        $this->assertTrue(
            $responseMessage->repliesTo($requestMessage),
            'repliesTo() should return true when correlation IDs match'
        );

        // Create another request with different correlation ID
        $otherRequest = new class extends Message {
            public function __construct(?string $correlationId = null)
            {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return ['correlationId' => $this->correlationId, 'type' => 'Request', 'data' => []];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($correlationId);
            }
        };

        // Test repliesTo with non-matching correlation
        $this->assertFalse(
            $responseMessage->repliesTo($otherRequest),
            'repliesTo() should return false when correlation IDs do not match'
        );
    }
}
