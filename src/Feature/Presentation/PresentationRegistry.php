<?php

declare(strict_types=1);

namespace Noem\State\Feature\Presentation;

use Noem\State\Middleware\Mesh;

/**
 * Mesh-based registry for RegionPresentation definitions.
 *
 * Provides registration, retrieval, and unregistration of presentation contracts
 * with schema validation enforcement.
 *
 * @template-extends Mesh<RegionPresentation, array<string, RegionPresentation>>
 */
class PresentationRegistry extends Mesh
{
    /**
     * @var array<string, RegionPresentation>
     */
    private array $presentations = [];

    /**
     * @var array<string, mixed> JSON Schema definitions indexed by field name
     */
    private array $schemas = [];

    /**
     * Register a presentation contract.
     *
     * @param RegionPresentation $presentation Presentation to register
     * @throws SchemaNotFoundException When presentation key not found in JsonSchema
     */
    public function register(RegionPresentation $presentation): void
    {
        // Validate key exists in schema
        if (!isset($this->schemas[$presentation->key])) {
            throw new SchemaNotFoundException(
                "Presentation key '{$presentation->key}' not found in JsonSchema. All presented fields must have corresponding schema definitions."
            );
        }

        $this->presentations[$presentation->key] = $presentation;
    }

    /**
     * Retrieve a presentation by key.
     *
     * @param string $key Presentation key
     * @return RegionPresentation|null Presentation if found, null otherwise
     */
    public function get(string $key): ?RegionPresentation
    {
        return $this->presentations[$key] ?? null;
    }

    /**
     * Retrieve all registered presentations.
     *
     * @return array<string, RegionPresentation> All presentations indexed by key
     */
    public function all(): array
    {
        return $this->presentations;
    }

    /**
     * Unregister a presentation by key.
     *
     * Idempotent - succeeds even if key doesn't exist.
     *
     * @param string $key Presentation key to remove
     */
    public function unregister(string $key): void
    {
        unset($this->presentations[$key]);
    }

    /**
     * Set schema definitions for validation.
     *
     * Called by PresentationFeature during initialization.
     *
     * @param array<string, mixed> $schemas Schema definitions indexed by field name
     * @internal
     */
    public function setSchemas(array $schemas): void
    {
        $this->schemas = $schemas;
    }

    /**
     * Get schema definition for a presentation key.
     *
     * @param string $key Presentation key
     * @return array<string, mixed>|null Schema definition or null if not found
     */
    public function getSchema(string $key): ?array
    {
        return $this->schemas[$key] ?? null;
    }
}
