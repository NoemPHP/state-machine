<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Scheduler checks timeout on each tick
 * Intent: Implements periodic timeout monitoring to detect and cancel exceeded tasks
 */
#[Group('async'), Group('unit'), Group('timeout')]
class TimeoutCheckingTest extends TestCase
{
    public function testSchedulerChecksTimeoutOnEachTick(): void
    {
        $scheduler = new CoroutineScheduler();
        $executionCount = 0;
        $config = new AsyncConfig(timeout: 0.05, priority: \Noem\State\Feature\Async\Priority::LOW); // 50ms timeout, 1 step per tick

        $generator = (function () use (&$executionCount) {
            while (true) {
                $executionCount++;
                yield 'test';
            }
        })();

        $task = $scheduler->enqueue($generator, $config);

        // Tick within timeout - should execute
        $scheduler->tick();
        $this->assertEquals(1, $executionCount, 'Task should execute within timeout');

        // Wait for timeout to expire
        usleep(60000); // 60ms

        // Tick after timeout - task should be cancelled and not execute
        $scheduler->tick();
        $this->assertEquals(1, $executionCount, 'Task should not execute after timeout');

        // Verify task was cancelled
        $this->assertTrue($task->isCancelled(), 'Task should be cancelled after timeout');
    }
}
