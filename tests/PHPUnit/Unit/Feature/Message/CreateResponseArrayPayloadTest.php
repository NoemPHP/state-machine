<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.createResponse() supports array payload format
 *
 * Spec: response-creation / Message.createResponse() supports array payload format
 * Intent: Enforces consistent payload structure aligned with JSON serialization format
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class CreateResponseArrayPayloadTest extends TestCase
{
    public function testCreateResponseSupportsArrayPayloadFormat(): void
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

        $responseClass = new class ('default', 0) extends Message {
            public function __construct(
                public readonly string $message,
                public readonly int $code,
                ?string $correlationId = null
            ) {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return [
                    'correlationId' => $this->correlationId,
                    'type' => 'Response',
                    'data' => ['message' => $this->message, 'code' => $this->code]
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($data['message'] ?? 'default', $data['code'] ?? 0, $correlationId);
            }
        };

        // Test with array payload
        $arrayPayload = ['message' => 'Success', 'code' => 200];
        $response = $requestMessage->createResponse($responseClass::class, $arrayPayload);

        $this->assertInstanceOf(Message::class, $response);
        $this->assertSame('Success', $response->message);
        $this->assertSame(200, $response->code);
        $this->assertSame($requestMessage->correlationId(), $response->correlationId());
    }
}
