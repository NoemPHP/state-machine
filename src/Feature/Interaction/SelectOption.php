<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

/**
 * Option for SelectRequest
 *
 * Value object representing a single selectable option with label and description.
 */
class SelectOption implements \JsonSerializable
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $description = null
    ) {
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            'label' => $this->label,
            'description' => $this->description,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['label'] ?? '',
            $data['description'] ?? null
        );
    }
}
