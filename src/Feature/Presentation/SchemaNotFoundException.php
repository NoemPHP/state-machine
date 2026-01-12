<?php

declare(strict_types=1);

namespace Noem\State\Feature\Presentation;

use RuntimeException;

/**
 * Exception thrown when attempting to register a presentation for a field
 * that doesn't have a corresponding JSON Schema definition.
 */
class SchemaNotFoundException extends RuntimeException
{
}
