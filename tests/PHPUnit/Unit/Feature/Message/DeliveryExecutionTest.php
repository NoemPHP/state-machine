<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.deliverResponse() invokes registered then() handlers with response
 *
 * Spec: promise-api / Message.deliverResponse() invokes registered then() handlers with response
 * Intent: Executes all registered handlers when matching response arrives, completing request-response cycle
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class DeliveryExecutionTest extends TestCase
{
    public function testDeliverResponseInvokesRegisteredThenHandlersWithResponse(): void
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

        $receivedResponse = null;
        $handler = function (Message $response) use (&$receivedResponse) {
            $receivedResponse = $response;
        };

        $message->then($handler);

        // Create and deliver response
        $response = $message->createResponse($message::class, []);
        $message->deliverResponse($response);

        $this->assertSame($response, $receivedResponse, 'deliverResponse() should invoke handlers with the response object');
    }
}
