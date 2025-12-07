<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.then() returns self for method chaining
 *
 * Spec: promise-api / Message.then() returns self for method chaining
 * Intent: Enables fluent interface for attaching multiple handlers or chaining configuration methods
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class ThenChainingTest extends TestCase
{
    public function testThenReturnsSelfForMethodChaining(): void
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

        $handler1 = function (Message $response) {
        };
        $handler2 = function (Message $response) {
        };

        // Test that then() returns self for chaining
        $result = $message->then($handler1)->then($handler2);

        $this->assertSame($message, $result, 'then() should return self for method chaining');
    }
}
