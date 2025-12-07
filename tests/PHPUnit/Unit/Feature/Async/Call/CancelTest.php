<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Call;

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.cancel terminates specified task
 */
#[Group('async'), Group('call-helpers')]
class CancelTest extends TestCase
{
    public function testCancelTerminatesTask(): void
    {
        $scheduler = new CoroutineScheduler();

        $targetSteps = [];
        $targetGen = (function () use (&$targetSteps) {
            $targetSteps[] = 1;
            yield;
            $targetSteps[] = 2;
            yield;
            $targetSteps[] = 3;
        })();

        $targetTask = $scheduler->enqueue($targetGen);

        // Tick to execute first step
        $scheduler->tick();
        $this->assertSame([1], $targetSteps);

        // Create a Call that will cancel the target task
        $cancelCall = Call::cancel($targetTask);

        // Execute the cancel call in a separate generator
        $cancellingGen = (function () use ($cancelCall) {
            yield $cancelCall;
        })();

        $scheduler->enqueue($cancellingGen);

        // Tick executes both: target task step 2 AND the cancel call
        $scheduler->tick();

        // After this tick, target executed step 2, then got cancelled
        $this->assertSame([1, 2], $targetSteps);

        // Target task should no longer exist in queue after cancellation
        // Further ticks should not add more steps
        $scheduler->tick();
        $scheduler->tick();

        $this->assertSame([1, 2], $targetSteps, 'Cancelled task should not execute after cancellation');
    }

    public function testCancelTriggersCompletionCallbacks(): void
    {
        $scheduler = new CoroutineScheduler();

        $completionCalled = false;

        $targetGen = (function () {
            yield 'step1';
            yield 'step2';
        })();

        $targetTask = $scheduler->enqueue($targetGen);
        $scheduler->onComplete($targetTask, function () use (&$completionCalled) {
            $completionCalled = true;
        });

        // Cancel the task via Call
        $cancelCall = Call::cancel($targetTask);

        $callerGen = (function () use ($cancelCall) {
            yield $cancelCall;
        })();

        $scheduler->enqueue($callerGen);
        $scheduler->tick();

        $this->assertTrue($completionCalled, 'Cancellation should trigger completion callbacks');
    }

    public function testCancelDoesNotAffectOtherTasks(): void
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

        // Cancel task1 via Call
        $cancelCall = Call::cancel($task1);
        $callerGen = (function () use ($cancelCall) {
            yield $cancelCall;
        })();
        $scheduler->enqueue($callerGen);

        // This tick: task1 executes B, task2 executes Y, then cancel executes
        $scheduler->tick();

        $this->assertSame(['A', 'B'], $task1Steps, 'Task executes before cancel in same tick');
        $this->assertSame(['X', 'Y'], $task2Steps);

        // Further ticks: task1 should not continue, task2 already finished
        $scheduler->tick();
        $this->assertSame(['A', 'B'], $task1Steps, 'Cancelled task should not continue after cancellation');
    }
}
