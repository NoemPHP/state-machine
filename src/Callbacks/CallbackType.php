<?php

declare(strict_types=1);

namespace Noem\State\Callbacks;

use Noem\State\MetaType;

/**
 * Abstract base type for callback channels using singleton pattern.
 *
 * Provides type-safe singleton instances for callback channels, enabling features
 * to define custom callback types (async, sync, priority, etc.) with identity semantics.
 * Extends MetaType to inherit singleton behavior and identity comparison methods.
 */
abstract class CallbackType extends MetaType
{
}
