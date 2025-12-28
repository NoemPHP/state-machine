<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities\Exception;

/**
 * Exception thrown when ability parameter validation fails
 *
 * Indicates that parameters passed to an ability invocation did not match
 * the required schema defined in the ability's parameterSchema.
 */
class SchemaValidationException extends \RuntimeException
{
}
