<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Finished singleton tasks are removed from tracking map
 * Intent: Cleans up completed tasks to prevent memory accumulation and allow task recreation
 */
#[Group('async'), Group('unit'), Group('singleton')]
class SingletonCleanupTest extends TestCase
{
    public function testFinishedSingletonTasksRemovedFromTrackingMap(): void
    {
        $scheduler = new CoroutineScheduler();
        $config = new AsyncConfig(singleton: true);

        $callback = function () {};

        // Create and finish a task
        $gen1 = (function () {
            yield 'test';
            // Generator completes
        })();
        $task1 = $scheduler->enqueue($gen1, $config, $callback);

        // Execute until task is finished
        $scheduler->tick();
        $scheduler->tick();

        $this->assertTrue($task1->isFinished(), 'Task should be finished');

        // Enqueue again with same callback - should create NEW task (not return finished one)
        $gen2 = (function () {
            yield 'new';
        })();
        $task2 = $scheduler->enqueue($gen2, $config, $callback);

        $this->assertNotSame($task1, $task2, 'Finished task should be cleaned up, new task created');
        $this->assertFalse($task2->isFinished(), 'New task should not be finished');
    }
}
