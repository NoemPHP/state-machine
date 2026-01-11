<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Override;

/**
 * Confirmation interaction request (Yes/No decision)
 *
 * Used when state machine needs a boolean confirmation from external agent.
 * Examples: approve deployment, confirm deletion, verify understanding.
 */
class ConfirmRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly bool $defaultValue = false,
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $interactionId = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $interactionId, $correlationId);
    }

    #[\Override]
    public function getType(): string
    {
        return 'confirm';
    }

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return [
            'type' => static::class,
            'correlationId' => $this->correlationId(),
            'data' => [
                'question' => $this->question,
                'defaultValue' => $this->defaultValue,
                'context' => $this->context,
                'timeoutMs' => $this->timeoutMs,
            ]
        ];
    }

    #[\Override]
    protected static function fromData(mixed $data, ?string $correlationId): static
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Data must be an array');
        }

        return new self(
            $data['question'] ?? '',
            $data['defaultValue'] ?? false,
            $data['context'] ?? null,
            $data['timeoutMs'] ?? null,
            null,  // interactionId not preserved in serialization
            $correlationId
        );
    }
}
