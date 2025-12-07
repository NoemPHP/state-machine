<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.then() registers response handler callbacks
 *
 * Spec: promise-api / Message.then() registers response handler callbacks
 * Intent: Provides fluent API for attaching response handlers without manual subscription setup
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class ThenRegistrationTest extends TestCase
{
    public function testThenRegistersResponseHandlerCallbacks(): void
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

        $handlerCalled = false;
        $handler = function (Message $response) use (&$handlerCalled) {
            $handlerCalled = true;
        };

        // Register handler using then()
        $message->then($handler);

        // Create a response and deliver it
        $response = $message->createResponse($message::class, []);
        $message->deliverResponse($response);

        $this->assertTrue($handlerCalled, 'Handler registered via then() should be called when response is delivered');
    }
}
