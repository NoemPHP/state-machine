<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Scheduler skips throttled tasks until interval elapses
 * Intent: Implements rate limiting by deferring execution until throttle period passes
 */
#[Group('async'), Group('unit'), Group('throttle')]
class SchedulerSkipsThrottleTest extends TestCase
{
    public function testSchedulerSkipsThrottledTasksUntilIntervalElapses(): void
    {
        $scheduler = new CoroutineScheduler();
        $executionCount = 0;
        $config = new AsyncConfig(throttle: 0.1, priority: \Noem\State\Feature\Async\Priority::LOW); // 100ms throttle, 1 step per tick

        $generator = (function () use (&$executionCount) {
            while (true) {
                $executionCount++;
                yield 'test';
            }
        })();

        $scheduler->enqueue($generator, $config);

        // First tick - should execute
        $scheduler->tick();
        $this->assertEquals(1, $executionCount, 'First execution should succeed');

        // Second tick immediately - should be throttled
        $scheduler->tick();
        $this->assertEquals(1, $executionCount, 'Second execution should be skipped due to throttle');

        // Wait for throttle interval to elapse
        usleep(150000); // 150ms

        // Third tick - should execute now
        $scheduler->tick();
        $this->assertEquals(2, $executionCount, 'Third execution should succeed after throttle interval');
    }
}
