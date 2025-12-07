<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler tracks completion callbacks per task
 */
#[Group('async'), Group('coroutine-scheduler')]
class TracksCompletionCallbacksTest extends TestCase
{
    public function testTracksCompletionCallbacks(): void
    {
        $scheduler = new CoroutineScheduler();

        $callback1Called = false;
        $callback2Called = false;

        $generator = (function () {
            yield 'value';
        })();

        $task = $scheduler->enqueue($generator);

        // Register multiple completion callbacks
        $scheduler->onComplete($task, function () use (&$callback1Called) {
            $callback1Called = true;
        });

        $scheduler->onComplete($task, function () use (&$callback2Called) {
            $callback2Called = true;
        });

        // Execute until completion
        $scheduler->tick(); // current()
        $scheduler->tick(); // send() - finishes
        $scheduler->tick(); // cancel() - triggers callbacks

        $this->assertTrue($callback1Called, 'First callback should be called');
        $this->assertTrue($callback2Called, 'Second callback should be called');
    }

    public function testCallbacksAreTaskSpecific(): void
    {
        $scheduler = new CoroutineScheduler();

        $task1Callback = false;
        $task2Callback = false;

        $gen1 = (function () {
            yield 'A';
        })();

        $gen2 = (function () {
            yield 'B';
            yield 'C';
        })();

        $task1 = $scheduler->enqueue($gen1);
        $task2 = $scheduler->enqueue($gen2);

        $scheduler->onComplete($task1, function () use (&$task1Callback) {
            $task1Callback = true;
        });

        $scheduler->onComplete($task2, function () use (&$task2Callback) {
            $task2Callback = true;
        });

        // Complete task1 (3 ticks: current, send-finish, cancel)
        $scheduler->tick();
        $scheduler->tick();
        $scheduler->tick();

        $this->assertTrue($task1Callback, 'Task1 callback should be called');
        $this->assertFalse($task2Callback, 'Task2 callback should not be called yet');
    }

    public function testThrowsExceptionForNonEnqueuedTask(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 'value';
        })();

        $task = $scheduler->enqueue($generator);

        // Complete and remove the task
        $scheduler->tick();
        $scheduler->tick();
        $scheduler->tick();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task is not enqueued');

        $scheduler->onComplete($task, function () {
        });
    }
}
