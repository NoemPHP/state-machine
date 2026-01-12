<?php

declare(strict_types=1);

namespace Noem\State\Feature\Presentation;

use JsonSerializable;

/**
 * Immutable value object representing a state machine presentation contract.
 *
 * Presentations expose internal context fields to external agents (AI, monitoring, debugging)
 * with rich metadata for rendering and schema validation.
 */
readonly class RegionPresentation implements JsonSerializable
{
    /**
     * @param string $key Context field identifier for value retrieval and schema validation
     * @param string $label Human-readable display name for UI rendering and documentation
     * @param string $intent Semantic meaning and purpose for context-aware rendering decisions
     * @param array<string, mixed>|null $metadata Extensible format hints (precision, unit, format)
     * @param callable|null $predicate Conditional logic for runtime filtering (Region -> bool)
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $intent,
        public ?array $metadata = null,
        public mixed $predicate = null,
    ) {
    }

    /**
     * Serialize presentation to JSON-compatible array.
     *
     * Excludes predicate property as callables are not serializable.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'intent' => $this->intent,
            'metadata' => $this->metadata,
        ];
    }
}
