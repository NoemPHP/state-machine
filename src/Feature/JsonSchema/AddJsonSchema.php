<?php

declare(strict_types=1);

namespace Noem\State\Feature\JsonSchema;

use Noem\State\BuildStep;
use Noem\State\Chains\Meta;
use Noem\State\Chains\Params;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Region;
use Noem\State\RegionBuilder;

/**
 * Build step that initializes JSON schema defaults and registers schema metadata
 *
 * This stores both:
 * 1. Default values in the context metadata (for ExtendedState)
 * 2. Schema definition in JsonSchemaMetaType (for schema discovery by other features)
 */
class AddJsonSchema implements BuildStep
{
    public function __construct(private readonly array $schema)
    {
    }

    #[\Override]
    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        $meta = $builder->chainMail->get(Meta::class);
        $region = $next($builder);

        // Store default values in context metadata
        $metadata = $meta->call(new Params\Meta($region, ContextMetaType::get()));
        foreach ($this->schema as $type) {
            $name = $type['name'];
            $default = $type['default'] ?? null;
            $metadata[$name] = $default;
        }

        // Store schema definition for discovery by other features (e.g., ContextBroadcastFeature)
        $schemaMeta = $meta->call(new Params\Meta($region, JsonSchemaMetaType::get()));
        $schemaMeta['schema'] = $this->schema;

        return $region;
    }
}
