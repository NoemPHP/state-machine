<?php

declare(strict_types=1);

namespace Noem\State\Feature\Logging;

use Noem\State\Middleware\Chain;

/**
 * Middleware chain for log entry processing
 *
 * LoggingChain intercepts and routes log entries through registered middleware,
 * enabling features like:
 * - Log filtering by level
 * - Log formatting and enrichment
 * - Routing to multiple backends (files, databases, external services)
 * - Context augmentation
 *
 * Default behavior: Silent no-op (logs are discarded unless middleware handles them)
 *
 * @extends Chain<LogParams, null>
 */
class LoggingChain extends Chain
{
    public function __construct()
    {
        // Default provider does nothing - logs are discarded unless middleware handles them
        /** @psalm-suppress UnusedClosureParam */
        // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        parent::__construct(fn(LogParams $params) => null);
    }
}
