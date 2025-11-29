<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Call;

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call.waitForSecs pauses task for specified duration
 */
#[Group('async'), Group('call-helpers')]
class WaitForSecsTest extends TestCase
{
    public function testWaitForSecsPausesTask(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $steps = [];
        
        $waitCall = Call::waitForSecs(1);
        
        $generator = (function () use ($waitCall, &$steps) {
            $steps[] = 'before-wait';
            yield $waitCall;
            $steps[] = 'after-wait';
        })();
        
        $task = $scheduler->enqueue($generator);
        
        // First tick: step before wait
        $scheduler->tick();
        $this->assertSame(['before-wait'], $steps);
        
        // Second tick: wait Call is invoked, task is paused, timer coroutine spawned
        $scheduler->tick();
        $this->assertSame(['before-wait'], $steps, 'Task should be paused during wait');
        
        // Task should remain paused for several ticks while timer runs
        $scheduler->tick();
        $this->assertSame(['before-wait'], $steps);
        
        $scheduler->tick();
        $this->assertSame(['before-wait'], $steps);
    }
    
    public function testWaitForSecsSpawnsTimerCoroutine(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $waitCall = Call::waitForSecs(0); // Very short wait
        
        $taskExecuted = false;
        
        $generator = (function () use ($waitCall, &$taskExecuted) {
            yield $waitCall;
            $taskExecuted = true;
        })();
        
        $scheduler->enqueue($generator);
        
        // The wait call should spawn a separate timer coroutine
        // After enough ticks, the task should resume
        $scheduler->tick();
        $this->assertFalse($taskExecuted);
        
        // Give it many ticks to complete (timer runs until time elapses)
        for ($i = 0; $i < 20; $i++) {
            $scheduler->tick();
            if ($taskExecuted) {
                break;
            }
        }
        
        $this->assertTrue($taskExecuted, 'Task should eventually resume after wait completes');
    }
    
    public function testWaitForSecsDoesNotBlockOtherTasks(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $waitingTaskSteps = [];
        $otherTaskSteps = [];
        
        $waitCall = Call::waitForSecs(1);
        
        $waitingGen = (function () use ($waitCall, &$waitingTaskSteps) {
            $waitingTaskSteps[] = 'before';
            yield $waitCall;
            $waitingTaskSteps[] = 'after';
        })();
        
        $otherGen = (function () use (&$otherTaskSteps) {
            $otherTaskSteps[] = 'step1';
            yield;
            $otherTaskSteps[] = 'step2';
            yield;
        })();
        
        $scheduler->enqueue($waitingGen);
        $scheduler->enqueue($otherGen);
        
        $scheduler->tick();
        $this->assertSame(['before'], $waitingTaskSteps);
        $this->assertSame(['step1'], $otherTaskSteps);
        
        $scheduler->tick();
        $this->assertSame(['before'], $waitingTaskSteps, 'Waiting task paused');
        $this->assertSame(['step1', 'step2'], $otherTaskSteps, 'Other task continues');
    }
}
