<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

/**
 * Type-safe priority levels for scheduler execution.
 *
 * Provides three priority levels with integer backing values that determine
 * execution frequency in the scheduler. Higher values execute more steps per tick.
 *
 * - LOW (1): Background tasks, minimal scheduler attention
 * - NORMAL (5): Default priority for most async operations
 * - HIGH (10): Critical tasks requiring responsive execution
 */
enum Priority: int
{
    case LOW = 1;
    case NORMAL = 5;
    case HIGH = 10;
}
