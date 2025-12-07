<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler removes completed tasks from queue
 */
#[Group('async'), Group('coroutine-scheduler')]
class RemovesCompletedTasksTest extends TestCase
{
    public function testRemovesCompletedTasks(): void
    {
        $scheduler = new CoroutineScheduler();

        $completionCallbackTriggered = false;

        $shortGen = (function () {
            yield 'value';
            // Completes after one yield
        })();

        $shortTask = $scheduler->enqueue($shortGen);

        // Set up completion callback to verify removal
        $scheduler->onComplete($shortTask, function () use (&$completionCallbackTriggered) {
            $completionCallbackTriggered = true;
        });

        // Tick 1: Task.run() calls current(), returns yielded value
        $scheduler->tick();
        $this->assertFalse($shortTask->isFinished(), 'Task should not be finished after first tick');
        $this->assertFalse($completionCallbackTriggered);

        // Tick 2: Task.run() calls send(), generator completes
        $scheduler->tick();
        $this->assertTrue($shortTask->isFinished(), 'Task should be finished after second tick');
        $this->assertFalse($completionCallbackTriggered, 'Callback not triggered yet');

        // Tick 3: Scheduler detects task is finished and calls cancel()
        $scheduler->tick();
        $this->assertTrue(
            $completionCallbackTriggered,
            'Completed task should trigger its completion callback when removed'
        );
    }

    public function testTriggersCompletionCallbacksBeforeRemoval(): void
    {
        $scheduler = new CoroutineScheduler();

        $callbackExecuted = false;

        $generator = (function () {
            yield 'only-step';
        })();

        $task = $scheduler->enqueue($generator);
        $scheduler->onComplete($task, function () use (&$callbackExecuted) {
            $callbackExecuted = true;
        });

        $this->assertFalse($callbackExecuted);

        // Tick 1: current() returns yielded value
        $scheduler->tick();
        $this->assertFalse($callbackExecuted);

        // Tick 2: send() advances generator to completion
        $scheduler->tick();
        $this->assertTrue($task->isFinished());
        $this->assertFalse($callbackExecuted);

        // Tick 3: cancel() is called and triggers callbacks
        $scheduler->tick();
        $this->assertTrue(
            $callbackExecuted,
            'Completion callback should be triggered when task is removed'
        );
    }
}
