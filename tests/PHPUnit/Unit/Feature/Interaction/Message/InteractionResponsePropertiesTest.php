<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\InteractionResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionResponse has readonly properties: value, cancelled
 * @see specs/features/interaction.yaml - message-types-base
 */
class InteractionResponsePropertiesTest extends TestCase
{
    public function testHasValueProperty(): void
    {
        $response = $this->createTestResponse('test_value');

        $this->assertSame('test_value', $response->value);
    }

    public function testValueCanBeNull(): void
    {
        $response = $this->createTestResponse(null);

        $this->assertNull($response->value);
    }

    public function testHasCancelledProperty(): void
    {
        $response = $this->createTestResponse('value', cancelled: true);

        $this->assertTrue($response->cancelled);
    }

    public function testCancelledDefaultsToFalse(): void
    {
        $response = $this->createTestResponse('value');

        $this->assertFalse($response->cancelled);
    }

    private function createTestResponse(
        mixed $value,
        bool $cancelled = false
    ): InteractionResponse {
        return new class ($value, $cancelled) extends InteractionResponse {
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
