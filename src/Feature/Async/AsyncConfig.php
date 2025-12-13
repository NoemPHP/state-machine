<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

/**
 * Immutable configuration object for async callback behavior.
 *
 * Provides comprehensive configuration for async operations including:
 * - Debouncing: Delay execution until trigger activity stops
 * - Throttling: Limit execution frequency with minimum interval
 * - Singleton: Prevent concurrent task execution for same callback
 * - Priority: Control scheduler execution frequency (LOW/NORMAL/HIGH)
 * - Timeout: Enforce maximum execution time limits
 *
 * All properties are readonly ensuring configuration cannot be modified after creation,
 * providing predictable async behavior throughout task lifecycle.
 */
final class AsyncConfig
{
    /**
     * @param float|null $debounce Seconds to wait after last trigger before execution (null = disabled)
     * @param float|null $throttle Minimum seconds between executions (null = disabled)
     * @param bool $singleton Prevent new task when callback has running task
     * @param Priority $priority Scheduler execution frequency (LOW=1, NORMAL=5, HIGH=10 steps per tick)
     * @param float|null $timeout Maximum execution time in seconds (null = no limit)
     *
     * @throws \InvalidArgumentException If validation fails
     */
    public function __construct(
        public readonly ?float $debounce = null,
        public readonly ?float $throttle = null,
        public readonly bool $singleton = false,
        public readonly Priority $priority = Priority::NORMAL,
        public readonly ?float $timeout = null,
    ) {
        if ($this->debounce !== null && $this->debounce < 0) {
            throw new \InvalidArgumentException('Debounce must be >= 0');
        }

        if ($this->throttle !== null && $this->throttle < 0) {
            throw new \InvalidArgumentException('Throttle must be >= 0');
        }

        if ($this->timeout !== null && $this->timeout <= 0) {
            throw new \InvalidArgumentException('Timeout must be > 0');
        }
    }
}
