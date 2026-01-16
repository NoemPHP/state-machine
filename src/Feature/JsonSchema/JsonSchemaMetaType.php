<?php

declare(strict_types=1);

namespace Noem\State\Feature\JsonSchema;

use Noem\State\MetaType;

/**
 * Meta type for storing JSON schema definitions
 *
 * This allows other features (like ContextBroadcastFeature) to discover
 * what schema properties were defined, enabling schema-based filtering.
 */
class JsonSchemaMetaType extends MetaType
{
}
