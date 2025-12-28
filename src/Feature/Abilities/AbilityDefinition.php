<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities;

/**
 * Immutable ability definition with name, description, schemas, handler, and optional predicate
 *
 * Represents a complete ability contract including validation schemas and execution logic.
 * Predicates enable conditional exposure based on region context.
 */
final readonly class AbilityDefinition
{
    /**
     * @param string $name Unique ability identifier
     * @param string $description Human-readable documentation
     * @param array $parameterSchema JSON Schema for parameter validation (empty array = no validation)
     * @param array $responseSchema JSON Schema for response validation
     * @param callable $handler Execution logic receiving parameters and returning response data
     * @param callable|null $predicate Optional conditional exposure callable receiving Region, returning bool
     */
    public function __construct(
        public string $name,
        public string $description,
        public array $parameterSchema,
        public array $responseSchema,
        public mixed $handler,
        public mixed $predicate = null,
    ) {
    }
}
