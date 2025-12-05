<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader\Helper;

/**
 * Trait for tracking recursion depth in include helpers to prevent infinite recursion.
 *
 * Provides automatic depth tracking with increment/decrement and MAX_INCLUDE_DEPTH enforcement.
 * Each helper instance maintains its own independent depth counter.
 */
trait DepthTracker
{
    /**
     * Maximum allowed include depth to prevent stack overflow from
     * circular or deeply nested includes.
     */
    private const int MAX_INCLUDE_DEPTH = 10;

    /**
     * Current recursion depth for this helper instance.
     */
    private int $currentDepth = 0;

    /**
     * Initialize the depth tracker with an inherited depth from a parent helper.
     *
     * @param int $inheritedDepth Starting depth (for nested helpers)
     */
    protected function initializeDepth(int $inheritedDepth): void
    {
        $this->currentDepth = $inheritedDepth;
    }

    /**
     * Track a recursive operation with automatic depth increment/decrement.
     *
     * Increments depth before callback, enforces MAX_INCLUDE_DEPTH,
     * and decrements depth after (even on exception).
     *
     * @template T
     * @param string $path File path being included (for error messages)
     * @param callable(): T $callback Operation to track
     * @return T Result from callback
     * @throws \RuntimeException When depth exceeds MAX_INCLUDE_DEPTH
     */
    protected function trackDepth(string $path, callable $callback): mixed
    {
        // Increment depth before operation
        $this->currentDepth++;

        // Enforce MAX_INCLUDE_DEPTH
        if ($this->currentDepth > self::MAX_INCLUDE_DEPTH) {
            throw new \RuntimeException(
                sprintf(
                    'Include depth exceeded MAX_INCLUDE_DEPTH (%d) while loading "%s"',
                    self::MAX_INCLUDE_DEPTH,
                    $path
                )
            );
        }

        try {
            // Execute the operation
            return $callback();
        } finally {
            // Always decrement depth (cleanup even on exception)
            $this->currentDepth--;
        }
    }

    /**
     * Get the current recursion depth.
     *
     * @return int Current nesting level (0 = top-level)
     */
    protected function getCurrentDepth(): int
    {
        return $this->currentDepth;
    }

    /**
     * Get the maximum allowed depth.
     *
     * @return int Maximum recursion depth limit
     */
    protected function getMaxDepth(): int
    {
        return self::MAX_INCLUDE_DEPTH;
    }
}
