<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Messages created via createResponse() inherit parent correlation ID
 *
 * Spec: uuid-correlation / Messages created via createResponse() inherit parent correlation ID
 * Intent: Ensures response messages carry same correlation ID as originating request for automatic matching
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class InheritedCorrelationTest extends TestCase
{
    public function testMessagesCreatedViaCreateResponseInheritParentCorrelationId(): void
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

        $responseClass = new class extends Message {
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
        };

        $responseMessage = $requestMessage->createResponse($responseClass::class, []);

        // Response should inherit the request's correlation ID
        $this->assertSame(
            $requestMessage->correlationId(),
            $responseMessage->correlationId(),
            'Response message should inherit parent correlation ID'
        );
    }
}
