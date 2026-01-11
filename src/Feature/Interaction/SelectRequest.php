<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Override;

/**
 * Select one option from a list
 *
 * Used when state machine needs user to choose one option from multiple choices.
 * Examples: choose database backend, select AI model, pick deployment environment.
 */
class SelectRequest extends InteractionRequest
{
    /**
     * @param string $question
     * @param array<string, SelectOption> $options Map of key => SelectOption
     * @param string|null $defaultKey
     * @param string|null $context
     * @param int|null $timeoutMs
     * @param string|null $interactionId
     * @param string|null $correlationId
     */
    public function __construct(
        string $question,
        public readonly array $options,
        public readonly ?string $defaultKey = null,
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
        return 'select';
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
                    fn(SelectOption $opt) => $opt->jsonSerialize(),
                    $this->options
                ),
                'defaultKey' => $this->defaultKey,
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
            $options[$key] = SelectOption::fromArray($optionData);
        }

        return new self(
            $data['question'] ?? '',
            $options,
            $data['defaultKey'] ?? null,
            $data['context'] ?? null,
            $data['timeoutMs'] ?? null,
            null,  // interactionId not preserved in serialization
            $correlationId
        );
    }
}
