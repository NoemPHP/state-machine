<?php

declare(strict_types=1);

namespace Noem\State\Callbacks;

/**
 * Default callback type for backward compatibility.
 *
 * Used when no explicit callback type is specified during registration.
 * Provides sensible defaults for callbacks without explicit type specification.
 */
final class DefaultCallbackType extends CallbackType
{
}
