<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

/**
 * Response to PromptRequest containing user input
 */
class PromptResponse extends InteractionResponse
{
    public function __construct(
        public readonly ?string $input,
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($input, $cancelled, $correlationId);
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            'type' => static::class,
            'correlationId' => $this->correlationId(),
            'data' => [
                'input' => $this->input,
                'cancelled' => $this->cancelled,
            ]
        ];
    }

    protected static function fromData(mixed $data, ?string $correlationId): static
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Data must be an array');
        }

        return new self(
            $data['input'] ?? null,
            $data['cancelled'] ?? false,
            $correlationId
        );
    }
}
