<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

/**
 * Response to ChoiceRequest containing selected option keys
 */
class ChoiceResponse extends InteractionResponse
{
    /**
     * @param array<string> $selectedKeys
     * @param bool $cancelled
     * @param string|null $correlationId
     */
    public function __construct(
        public readonly array $selectedKeys,
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($selectedKeys, $cancelled, $correlationId);
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            'type' => static::class,
            'correlationId' => $this->correlationId(),
            'data' => [
                'selectedKeys' => $this->selectedKeys,
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
            $data['selectedKeys'] ?? [],
            $data['cancelled'] ?? false,
            $correlationId
        );
    }
}
