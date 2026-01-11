<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

/**
 * Immutable interaction contract definition with metadata
 *
 * Value object describing expected interaction with complete contract information
 * for discovery, validation, and UI generation by external agents.
 */
final readonly class InteractionDefinition implements \JsonSerializable
{
    /**
     * @param string $id Unique interaction identifier for lookup and matching
     * @param string $type Interaction pattern type ('confirm', 'select', 'choice', 'prompt')
     * @param string $state Associated state for state-scoped discovery queries
     * @param string $question Question text or template
     * @param array|null $options Available options for select/choice interactions
     * @param array|null $constraints Validation constraints (minSelections, maxSelections, regex patterns)
     * @param array|null $metadata Extensible metadata (help text, defaults, placeholder)
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $state,
        public string $question,
        public ?array $options = null,
        public ?array $constraints = null,
        public ?array $metadata = null
    ) {
    }

    /**
     * Serialize to JSON-compatible array
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'state' => $this->state,
            'question' => $this->question,
            'options' => $this->options,
            'constraints' => $this->constraints,
            'metadata' => $this->metadata,
        ];
    }
}
