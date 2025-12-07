<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message subclasses must implement jsonSerialize() method
 *
 * Spec: json-serialization / Message subclasses must implement jsonSerialize() method
 * Intent: Forces explicit serialization strategy per message type for type-specific formatting control
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class JsonSerializeRequiredTest extends TestCase
{
    public function testMessageSubclassesMustImplementJsonSerializeMethod(): void
    {
        // Verify that the abstract Message class declares jsonSerialize as abstract
        $reflection = new \ReflectionClass(Message::class);
        $method = $reflection->getMethod('jsonSerialize');

        $this->assertTrue($method->isAbstract(), 'jsonSerialize() should be declared as abstract in Message class');
    }

    public function testConcreteMessageSubclassImplementsJsonSerialize(): void
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

        $this->assertTrue(method_exists($message, 'jsonSerialize'), 'Concrete Message subclass must implement jsonSerialize()');
    }
}
