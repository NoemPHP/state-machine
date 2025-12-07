<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.createResponse() creates new message instance with same correlation ID
 *
 * Spec: response-creation / Message.createResponse() creates new message instance with same correlation ID
 * Intent: Provides type-safe API for generating correlated responses without manual ID management
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class CreateResponseCorrelationTest extends TestCase
{
    public function testCreateResponseCreatesNewMessageInstanceWithSameCorrelationId(): void
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

        $response = $requestMessage->createResponse($responseClass, []);

        $this->assertInstanceOf(Message::class, $response);
        $this->assertSame($requestMessage->correlationId(), $response->correlationId(), 'Response should have same correlation ID as request');
    }
}
