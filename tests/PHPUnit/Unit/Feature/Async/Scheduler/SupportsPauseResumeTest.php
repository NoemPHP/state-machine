<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler supports task pause and resume
 */
#[Group('async'), Group('coroutine-scheduler')]
class SupportsPauseResumeTest extends TestCase
{
    public function testSupportsPauseResume(): void
    {
        $scheduler = new CoroutineScheduler();

        $steps = [];

        $generator = (function () use (&$steps) {
            $steps[] = 1;
            yield;
            $steps[] = 2;
            yield;
            $steps[] = 3;
            yield;
        })();

        $task = $scheduler->enqueue($generator);

        // Step 1 executes
        $scheduler->tick();
        $this->assertSame([1], $steps);

        // Pause the task
        $scheduler->pause($task);

        // Task should not execute when paused
        $scheduler->tick();
        $this->assertSame([1], $steps, 'Paused task should not execute');

        $scheduler->tick();
        $this->assertSame([1], $steps, 'Task should remain paused');

        // Resume the task
        $scheduler->resume($task);

        // Step 2 should execute after resume
        $scheduler->tick();
        $this->assertSame([1, 2], $steps, 'Resumed task should continue execution');

        // Step 3 should execute normally
        $scheduler->tick();
        $this->assertSame([1, 2, 3], $steps);
    }

    public function testPauseDoesNotAffectOtherTasks(): void
    {
        $scheduler = new CoroutineScheduler();

        $task1Steps = [];
        $task2Steps = [];

        $gen1 = (function () use (&$task1Steps) {
            $task1Steps[] = 'A';
            yield;
            $task1Steps[] = 'B';
            yield;
        })();

        $gen2 = (function () use (&$task2Steps) {
            $task2Steps[] = 'X';
            yield;
            $task2Steps[] = 'Y';
            yield;
        })();

        $task1 = $scheduler->enqueue($gen1);
        $task2 = $scheduler->enqueue($gen2);

        $scheduler->tick();
        $this->assertSame(['A'], $task1Steps);
        $this->assertSame(['X'], $task2Steps);

        // Pause only task1
        $scheduler->pause($task1);

        $scheduler->tick();
        $this->assertSame(['A'], $task1Steps, 'Paused task should not advance');
        $this->assertSame(['X', 'Y'], $task2Steps, 'Other tasks should continue');
    }
}
