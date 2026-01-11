<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest class extends Message
 * @see specs/features/interaction.yaml - message-types-base
 */
class InteractionRequestExtendsMessageTest extends TestCase
{
    public function testInteractionRequestExtendsMessage(): void
    {
        $request = $this->createTestRequest('Test question?');

        $this->assertInstanceOf(Message::class, $request);
    }

    private function createTestRequest(string $question): InteractionRequest
    {
        return new class ($question) extends InteractionRequest {
            public function getType(): string
            {
                return 'test';
            }

            public function jsonSerialize(): mixed
            {
                return [
                    'type' => static::class,
                    'correlationId' => $this->correlationId(),
                    'data' => [
                        'question' => $this->question,
                        'context' => $this->context,
                        'timeoutMs' => $this->timeoutMs,
                    ]
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new self(
                    $data['question'] ?? '',
                    $data['context'] ?? null,
                    $data['timeoutMs'] ?? null,
                    $correlationId
                );
            }
        };
    }
}
