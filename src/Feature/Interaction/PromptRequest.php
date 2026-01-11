<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Override;

/**
 * Free-form text input request
 *
 * Used when state machine needs free-form text input from user.
 * Examples: gather description, ask question, collect feedback.
 */
class PromptRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly ?string $placeholder = null,
        public readonly ?string $defaultValue = null,
        public readonly ?string $validation = null,
        public readonly bool $multiline = false,
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $interactionId = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $interactionId, $correlationId);
    }

    #[Override]
    public function getType(): string
    {
        return 'prompt';
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            'type' => static::class,
            'correlationId' => $this->correlationId(),
            'data' => [
                'question' => $this->question,
                'placeholder' => $this->placeholder,
                'defaultValue' => $this->defaultValue,
                'validation' => $this->validation,
                'multiline' => $this->multiline,
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
            $data['placeholder'] ?? null,
            $data['defaultValue'] ?? null,
            $data['validation'] ?? null,
            $data['multiline'] ?? false,
            $data['context'] ?? null,
            $data['timeoutMs'] ?? null,
            null,  // interactionId not preserved in serialization
            $correlationId
        );
    }
}
