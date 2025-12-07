<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.createResponse() passes correlation ID to fromData() method
 *
 * Spec: response-creation / Message.createResponse() passes correlation ID to fromData() method
 * Intent: Ensures response inherits request correlation automatically during construction
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class CreateResponseIdPassingTest extends TestCase
{
    public function testCreateResponsePassesCorrelationIdToFromDataMethod(): void
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

        $response = $requestMessage->createResponse($responseClass::class, []);

        // Verify correlation ID was passed through fromData
        $this->assertSame(
            $requestMessage->correlationId(),
            $response->correlationId(),
            'createResponse should pass correlation ID to fromData method'
        );
    }
}
