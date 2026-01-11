<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

/**
 * Confirmation interaction response
 *
 * Response to ConfirmRequest containing boolean decision.
 */
class ConfirmResponse extends InteractionResponse
{
    public function __construct(
        public readonly bool $confirmed,
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($confirmed, $cancelled, $correlationId);
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            'type' => static::class,
            'correlationId' => $this->correlationId(),
            'data' => [
                'confirmed' => $this->confirmed,
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
            $data['confirmed'] ?? false,
            $data['cancelled'] ?? false,
            $correlationId
        );
    }
}
