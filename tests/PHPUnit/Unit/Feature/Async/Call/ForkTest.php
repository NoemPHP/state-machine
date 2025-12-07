<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Call;

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.fork spawns independent coroutine without waiting
 */
#[Group('async'), Group('call-helpers')]
class ForkTest extends TestCase
{
    public function testForkSpawnsIndependentTask(): void
    {
        $scheduler = new CoroutineScheduler();

        $parentSteps = [];
        $forkedSteps = [];

        $forkedGenerator = function () use (&$forkedSteps) {
            $forkedSteps[] = 'forked-1';
            yield;
            $forkedSteps[] = 'forked-2';
            yield;
        };

        $forkCall = Call::fork($forkedGenerator);

        $parentGen = (function () use ($forkCall, &$parentSteps) {
            $parentSteps[] = 'parent-1';
            yield $forkCall; // Fork is invoked when this is yielded
            $parentSteps[] = 'parent-2';
            yield;
        })();

        $scheduler->enqueue($parentGen);

        // First tick: parent executes step 1, yields fork Call
        // Fork Call is invoked immediately, spawning forked task
        // Forked task also executes in this tick (SplObjectStorage iteration includes new items)
        $scheduler->tick();
        $this->assertSame(['parent-1'], $parentSteps);
        $this->assertSame(['forked-1'], $forkedSteps, 'Forked task executes in same tick as fork Call');

        // Second tick: parent continues to step 2, forked task continues
        $scheduler->tick();
        $this->assertSame(['parent-1', 'parent-2'], $parentSteps);
        $this->assertSame(['forked-1', 'forked-2'], $forkedSteps);
    }

    public function testForkDoesNotBlockParent(): void
    {
        $scheduler = new CoroutineScheduler();

        $parentStep2 = false;
        $forkedStarted = false;

        $forkedGenerator = function () use (&$forkedStarted) {
            $forkedStarted = true;
            yield;
            yield;
            yield;
        };

        $forkCall = Call::fork($forkedGenerator);

        $parentGen = (function () use ($forkCall, &$parentStep2) {
            yield $forkCall;
            $parentStep2 = true; // Parent continues without waiting
        })();

        $scheduler->enqueue($parentGen);

        // First tick: fork is invoked, parent continues, forked task starts
        $scheduler->tick();
        $this->assertTrue($forkedStarted, 'Forked task should start');
        $this->assertFalse($parentStep2, 'Parent has not yet executed step 2');

        // Second tick: parent completes step 2, forked task continues
        $scheduler->tick();
        $this->assertTrue($parentStep2, 'Parent continues without blocking on forked task');
    }

    public function testForkInjectsTaskReference(): void
    {
        $scheduler = new CoroutineScheduler();

        $receivedTask = null;

        $forkedGenerator = function () {
            yield 'forked-value';
        };

        $forkCall = Call::fork($forkedGenerator);

        $parentGen = (function () use ($forkCall, &$receivedTask) {
            $receivedTask = yield $forkCall;
            yield; // Need another yield so parent runs again to actually receive the value
        })();

        $parentTask = $scheduler->enqueue($parentGen);

        // First tick: fork Call is invoked, sets forked task as send value to parent
        $scheduler->tick();
        $this->assertNull($receivedTask, 'Value not yet received by generator');

        // Second tick: parent resumes and receives the sent value
        $scheduler->tick();

        $this->assertInstanceOf(Task::class, $receivedTask, 'Fork should inject forked task reference via setSendValue');
        $this->assertNotSame($parentTask, $receivedTask, 'Forked task should be different from parent task');
    }
}
