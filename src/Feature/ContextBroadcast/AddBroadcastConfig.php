<?php

declare(strict_types=1);

namespace Noem\State\Feature\ContextBroadcast;

use Noem\State\BuildStep;
use Noem\State\Chains\Meta;
use Noem\State\Chains\Params;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * Build step that stores broadcast configuration for runtime access
 *
 * Stores:
 * - Region-level broadcast enabled/disabled flag
 * - Property-level broadcast settings from schema
 */
class AddBroadcastConfig implements BuildStep
{
    /**
     * @param bool $regionBroadcast Region-level broadcast flag
     * @param array<int, array<string, mixed>> $schema Schema array
     * @param bool $schemaPresent Whether schema key was present (even if empty)
     * @param Meta $meta Meta chain for storage
     */
    public function __construct(
        private readonly bool $regionBroadcast,
        private readonly array $schema,
        private readonly bool $schemaPresent,
        private readonly Meta $meta
    ) {
    }

    #[\Override]
    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        $region = $next($builder);

        // Store broadcast config in metadata for runtime access
        $broadcastMeta = $this->meta->call(
            new Params\Meta($region, BroadcastConfigMetaType::get())
        );

        $broadcastMeta['enabled'] = $this->regionBroadcast;

        // Set 'properties' key if schema key was present in config (indicates JsonSchemaFeature loaded)
        // Note: We use $this->schemaPresent flag to distinguish between:
        // - schema key not present at all → no filtering (broadcast all)
        // - schema: [] → filtering with zero allowed properties (broadcast none)
        // - schema: [...] → filtering with specified properties
        if ($this->schemaPresent) {
            // Build the properties array first, then assign
            // (Mesh doesn't support nested modification via ArrayAccess)
            $properties = [];
            foreach ($this->schema as $entry) {
                $name = $entry['name'];
                // Default to true if not specified
                $properties[$name] = $entry['broadcast'] ?? true;
            }
            $broadcastMeta['properties'] = $properties;
        }

        return $region;
    }
}
