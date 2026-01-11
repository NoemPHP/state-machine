<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

/**
 * Response to SelectRequest containing selected option key
 */
class SelectResponse extends InteractionResponse
{
    public function __construct(
        public readonly ?string $selectedKey,
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($selectedKey, $cancelled, $correlationId);
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            'type' => static::class,
            'correlationId' => $this->correlationId(),
            'data' => [
                'selectedKey' => $this->selectedKey,
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
            $data['selectedKey'] ?? null,
            $data['cancelled'] ?? false,
            $correlationId
        );
    }
}
