<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler skips paused tasks during tick
 */
#[Group('async'), Group('coroutine-scheduler')]
class SkipsPausedTasksTest extends TestCase
{
    public function testSkipsPausedTasks(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $executed = [];
        
        $pausedGen = (function () use (&$executed) {
            $executed[] = 'paused-1';
            yield;
            $executed[] = 'paused-2';
            yield;
        })();
        
        $activeGen = (function () use (&$executed) {
            $executed[] = 'active-1';
            yield;
            $executed[] = 'active-2';
            yield;
        })();
        
        $pausedTask = $scheduler->enqueue($pausedGen);
        $activeTask = $scheduler->enqueue($activeGen);
        
        // First tick - both execute
        $scheduler->tick();
        $this->assertSame(['paused-1', 'active-1'], $executed);
        
        // Pause the first task
        $scheduler->pause($pausedTask);
        
        // Second tick - only active task executes
        $scheduler->tick();
        $this->assertSame(['paused-1', 'active-1', 'active-2'], $executed,
            'Paused task should not execute during tick');
    }
    
    public function testResumedTaskExecutesAgain(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $steps = [];
        
        $generator = (function () use (&$steps) {
            $steps[] = 'step1';
            yield;
            $steps[] = 'step2';
            yield;
            $steps[] = 'step3';
        })();
        
        $task = $scheduler->enqueue($generator);
        
        $scheduler->tick();
        $this->assertSame(['step1'], $steps);
        
        $scheduler->pause($task);
        $scheduler->tick();
        $this->assertSame(['step1'], $steps, 'Paused task should not execute');
        
        $scheduler->resume($task);
        $scheduler->tick();
        $this->assertSame(['step1', 'step2'], $steps, 'Resumed task should execute');
    }
}
