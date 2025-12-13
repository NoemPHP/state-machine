<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Debounce timer starts when task is enqueued
 * Intent: Establishes baseline for debounce delay measurement from enqueue time
 */
#[Group('async'), Group('unit'), Group('debounce')]
class DebounceTimerStartTest extends TestCase
{
    public function testDebounceTimerStartsOnEnqueue(): void
    {
        $scheduler = new CoroutineScheduler();
        $config = new AsyncConfig(debounce: 1.0);

        $generator = (function () {
            yield 'test';
        })();

        $beforeEnqueue = microtime(true);
        $task = $scheduler->enqueue($generator, $config);
        $afterEnqueue = microtime(true);

        // Verify timer was set
        $timerValue = $task->getDebounceTime();
        $this->assertNotNull($timerValue, 'Debounce timer should be set on enqueue');

        // Verify timer is within reasonable range (enqueue happened between before and after)
        $this->assertGreaterThanOrEqual($beforeEnqueue, $timerValue);
        $this->assertLessThanOrEqual($afterEnqueue, $timerValue);
    }
}
