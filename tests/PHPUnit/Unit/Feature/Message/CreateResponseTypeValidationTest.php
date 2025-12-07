<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.createResponse() validates response class extends Message
 *
 * Spec: response-creation / Message.createResponse() validates response class extends Message
 * Intent: Ensures type safety by rejecting invalid response types at runtime with clear error messages
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class CreateResponseTypeValidationTest extends TestCase
{
    public function testCreateResponseValidatesResponseClassExtendsMessage(): void
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Response class must exist and extend');

        // Try to create response with non-Message class
        $requestMessage->createResponse(\stdClass::class, []);
    }

    public function testCreateResponseValidatesResponseClassExists(): void
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

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Response class must exist and extend');

        // Try to create response with non-existent class
        $requestMessage->createResponse('NonExistentClass', []);
    }
}
