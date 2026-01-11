<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\InteractionRequest;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest has abstract getType() method returning interaction type identifier
 * @see specs/features/interaction.yaml - message-types-base
 */
class InteractionRequestGetTypeTest extends TestCase
{
    public function testGetTypeIsAbstract(): void
    {
        $reflection = new \ReflectionClass(InteractionRequest::class);
        $method = $reflection->getMethod('getType');

        $this->assertTrue($method->isAbstract());
    }

    public function testGetTypeReturnsString(): void
    {
        $request = $this->createTestRequest('Test?');

        $this->assertIsString($request->getType());
    }

    public function testSubclassDefinesTypeIdentifier(): void
    {
        $request = $this->createTestRequest('Test?');

        $this->assertSame('test_type', $request->getType());
    }

    private function createTestRequest(string $question): InteractionRequest
    {
        return new class ($question) extends InteractionRequest {
            public function getType(): string
            {
                return 'test_type';
            }

            public function jsonSerialize(): mixed
            {
                return [
                    'type' => static::class,
                    'correlationId' => $this->correlationId(),
                    'data' => ['question' => $this->question]
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new self($data['question'] ?? '', null, null, $correlationId);
            }
        };
    }
}
