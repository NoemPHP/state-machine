<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\TestCase;

final class PreventsSingletonDuplicatesTest extends TestCase
{
    public function testSingletonPreventsNewTaskWhenCallbackHasRunningTask(): void
    {
        $scheduler = new CoroutineScheduler();

        $callback = function () {};
        $config = new AsyncConfig(singleton: true);

        $gen1 = (function () {
            yield 1;
            yield 2;
        })();

        $task1 = $scheduler->enqueue($gen1, $config, $callback);

        // Try to enqueue again with same callback
        $gen2 = (function () {
            yield 3;
        })();

        $task2 = $scheduler->enqueue($gen2, $config, $callback);

        // Should return existing task, not create new one
        $this->assertSame($task1, $task2);
    }

    public function testSingletonAllowsNewTaskAfterPreviousFinishes(): void
    {
        $scheduler = new CoroutineScheduler();

        $callback = function () {};
        $config = new AsyncConfig(singleton: true);

        $gen1 = (function () {
            yield 1;
        })();

        $task1 = $scheduler->enqueue($gen1, $config, $callback);

        // Complete the task
        $scheduler->tick();
        $scheduler->tick();

        $this->assertTrue($task1->isFinished());

        // Now should be able to create new task
        $gen2 = (function () {
            yield 2;
        })();

        $task2 = $scheduler->enqueue($gen2, $config, $callback);

        $this->assertNotSame($task1, $task2);
    }

    public function testNonSingletonAllowsMultipleTasksForSameCallback(): void
    {
        $scheduler = new CoroutineScheduler();

        $callback = function () {};
        $config = new AsyncConfig(singleton: false); // Not singleton

        $gen1 = (function () {
            yield 1;
        })();
        $gen2 = (function () {
            yield 2;
        })();

        $task1 = $scheduler->enqueue($gen1, $config, $callback);
        $task2 = $scheduler->enqueue($gen2, $config, $callback);

        // Should create different tasks even with same callback
        $this->assertNotSame($task1, $task2);
    }

    public function testSingletonUsesCallbackReferenceForIdentity(): void
    {
        $scheduler = new CoroutineScheduler();

        $config = new AsyncConfig(singleton: true);

        $callback1 = function () {};
        $callback2 = function () {}; // Different instance

        $gen1 = (function () {
            yield 1;
        })();
        $gen2 = (function () {
            yield 2;
        })();

        $task1 = $scheduler->enqueue($gen1, $config, $callback1);
        $task2 = $scheduler->enqueue($gen2, $config, $callback2);

        // Different callback instances = different tasks allowed
        $this->assertNotSame($task1, $task2);
    }
}
