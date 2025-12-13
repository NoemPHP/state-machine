<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Noem\State\Callbacks\CallbackType;

/**
 * Dedicated channel for asynchronous callbacks.
 *
 * Defines the async callback type that separates async operations from sync callbacks,
 * enabling zero-overhead synchronous execution and explicit async configuration.
 * Uses singleton pattern inherited from MetaType for efficient type comparisons.
 */
final class AsyncCallbackType extends CallbackType
{
}
