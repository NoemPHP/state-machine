<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\InteractionRequest;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest has readonly properties: question, context, timeoutMs
 * @see specs/features/interaction.yaml - message-types-base
 */
class InteractionRequestPropertiesTest extends TestCase
{
    public function testHasQuestionProperty(): void
    {
        $request = $this->createTestRequest('What is your name?');

        $this->assertSame('What is your name?', $request->question);
    }

    public function testHasOptionalContextProperty(): void
    {
        $request = $this->createTestRequest(
            'Proceed?',
            context: 'This is important'
        );

        $this->assertSame('This is important', $request->context);
    }

    public function testContextDefaultsToNull(): void
    {
        $request = $this->createTestRequest('Proceed?');

        $this->assertNull($request->context);
    }

    public function testHasOptionalTimeoutMsProperty(): void
    {
        $request = $this->createTestRequest(
            'Proceed?',
            timeoutMs: 30000
        );

        $this->assertSame(30000, $request->timeoutMs);
    }

    public function testTimeoutMsDefaultsToNull(): void
    {
        $request = $this->createTestRequest('Proceed?');

        $this->assertNull($request->timeoutMs);
    }

    private function createTestRequest(
        string $question,
        ?string $context = null,
        ?int $timeoutMs = null
    ): InteractionRequest {
        return new class ($question, $context, $timeoutMs) extends InteractionRequest {
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
