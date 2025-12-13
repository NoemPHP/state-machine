<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Timed-out tasks are removed from scheduler queue
 * Intent: Stops further execution of cancelled tasks, freeing scheduler resources
 */
#[Group('async'), Group('unit'), Group('timeout')]
class TimeoutRemovalTest extends TestCase
{
    public function testTimedOutTasksRemovedFromSchedulerQueue(): void
    {
        $scheduler = new CoroutineScheduler();
        $config = new AsyncConfig(timeout: 0.05); // 50ms timeout

        $generator = (function () {
            while (true) {
                yield 'test';
            }
        })();

        $task = $scheduler->enqueue($generator, $config);

        // Wait for timeout to expire
        usleep(60000); // 60ms

        // Tick - task should be removed
        $scheduler->tick();

        // Task should be cancelled (no longer enqueued, won't execute further)
        $this->assertTrue($task->isCancelled(), 'Timed out task should be cancelled');
    }
}
