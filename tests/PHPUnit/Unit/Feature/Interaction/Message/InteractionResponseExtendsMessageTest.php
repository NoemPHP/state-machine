<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\InteractionResponse;
use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionResponse class extends Message
 * @see specs/features/interaction.yaml - message-types-base
 */
class InteractionResponseExtendsMessageTest extends TestCase
{
    public function testInteractionResponseExtendsMessage(): void
    {
        $response = $this->createTestResponse('test_value');

        $this->assertInstanceOf(Message::class, $response);
    }

    private function createTestResponse(mixed $value): InteractionResponse
    {
        return new class ($value) extends InteractionResponse {
            public function jsonSerialize(): mixed
            {
                return [
                    'type' => static::class,
                    'correlationId' => $this->correlationId(),
                    'data' => [
                        'value' => $this->value,
                        'cancelled' => $this->cancelled,
                    ]
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new self(
                    $data['value'] ?? null,
                    $data['cancelled'] ?? false,
                    $correlationId
                );
            }
        };
    }
}
