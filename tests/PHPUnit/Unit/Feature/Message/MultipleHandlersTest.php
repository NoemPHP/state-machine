<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that multiple then() handlers execute in registration order
 *
 * Spec: promise-api / Multiple then() handlers execute in registration order
 * Intent: Supports multiple observers per message with predictable execution sequence
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class MultipleHandlersTest extends TestCase
{
    public function testMultipleThenHandlersExecuteInRegistrationOrder(): void
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

        $executionOrder = [];

        $handler1 = function (Message $response) use (&$executionOrder) {
            $executionOrder[] = 1;
        };

        $handler2 = function (Message $response) use (&$executionOrder) {
            $executionOrder[] = 2;
        };

        $handler3 = function (Message $response) use (&$executionOrder) {
            $executionOrder[] = 3;
        };

        // Register multiple handlers
        $message->then($handler1)->then($handler2)->then($handler3);

        // Deliver response
        $response = $message->createResponse($message::class, []);
        $message->deliverResponse($response);

        // Verify execution order
        $this->assertSame([1, 2, 3], $executionOrder, 'Handlers should execute in registration order');
    }
}
