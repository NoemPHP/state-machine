<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.createResponse() calls target class fromData() method with payload
 *
 * Spec: response-creation / Message.createResponse() calls target class fromData() method with payload
 * Intent: Delegates construction to response class for proper initialization and validation
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class CreateResponseFromDataTest extends TestCase
{
    public function testCreateResponseCallsTargetClassFromDataMethodWithPayload(): void
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

        $responseClass = new class ('default') extends Message {
            public function __construct(
                public readonly string $message,
                ?string $correlationId = null
            ) {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return ['correlationId' => $this->correlationId, 'type' => 'Response', 'data' => ['message' => $this->message]];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($data['message'] ?? 'default', $correlationId);
            }
        };

        $payload = ['message' => 'Response payload'];
        $response = $requestMessage->createResponse($responseClass::class, $payload);

        // Verify fromData was called with payload
        $this->assertSame('Response payload', $response->message);
    }
}
