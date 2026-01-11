<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Override;

/**
 * Choose multiple options from a list
 */
class ChoiceRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly array $options,
        public readonly array $defaultKeys = [],
        public readonly ?int $minSelections = null,
        public readonly ?int $maxSelections = null,
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
        return 'choice';
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return [
            'type' => static::class,
            'correlationId' => $this->correlationId(),
            'data' => [
                'question' => $this->question,
                'options' => array_map(
                    fn(ChoiceOption $opt) => $opt->jsonSerialize(),
                    $this->options
                ),
                'defaultKeys' => $this->defaultKeys,
                'minSelections' => $this->minSelections,
                'maxSelections' => $this->maxSelections,
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

        $options = [];
        foreach (($data['options'] ?? []) as $key => $optionData) {
            $options[$key] = ChoiceOption::fromArray($optionData);
        }

        return new self(
            $data['question'] ?? '',
            $options,
            $data['defaultKeys'] ?? [],
            $data['minSelections'] ?? null,
            $data['maxSelections'] ?? null,
            $data['context'] ?? null,
            $data['timeoutMs'] ?? null,
            null,  // interactionId not preserved in serialization
            $correlationId
        );
    }
}
