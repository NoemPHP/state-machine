<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

/**
 * Option for ChoiceRequest (multiple selection)
 *
 * Value object representing a single choice option with label, description, and recommendation flag.
 */
class ChoiceOption implements \JsonSerializable
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $description = null,
        public readonly bool $recommended = false
    ) {
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            'label' => $this->label,
            'description' => $this->description,
            'recommended' => $this->recommended,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['label'] ?? '',
            $data['description'] ?? null,
            $data['recommended'] ?? false
        );
    }
}
