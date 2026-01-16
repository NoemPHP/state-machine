<?php

declare(strict_types=1);

namespace Noem\State\Feature\Logging;

use Noem\State\Region;

/**
 * Parameters for log entries in LoggingChain
 *
 * Encapsulates all data for a single log entry including:
 * - Log level (info, warning, error, etc.)
 * - Message text
 * - Optional context array
 * - Timestamp (automatically captured)
 * - Current state name (from region)
 * - Region reference (for path/context)
 */
class LogParams
{
    public readonly \DateTimeImmutable $timestamp;
    public readonly string $stateName;

    /**
     * @param Region $region State machine region where log originated
     * @param string $level Log level (info, debug, warning, error, etc.)
     * @param string $message Log message
     * @param array<string, mixed>|null $context Optional context data
     */
    public function __construct(
        public readonly Region $region,
        public readonly string $level,
        public readonly string $message,
        public readonly ?array $context = null,
    ) {
        $this->timestamp = new \DateTimeImmutable();
        $this->stateName = $region->currentState();
    }
}
