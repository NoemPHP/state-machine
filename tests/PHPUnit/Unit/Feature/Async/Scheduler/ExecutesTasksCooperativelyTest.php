<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler executes tasks cooperatively during tick
 */
#[Group('async'), Group('coroutine-scheduler')]
class ExecutesTasksCooperativelyTest extends TestCase
{
    public function testExecutesTasksCooperatively(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $executed = [];
        
        $task1 = (function () use (&$executed) {
            $executed[] = 'task1-step1';
            yield;
            $executed[] = 'task1-step2';
            yield;
        })();
        
        $task2 = (function () use (&$executed) {
            $executed[] = 'task2-step1';
            yield;
            $executed[] = 'task2-step2';
            yield;
        })();
        
        $scheduler->enqueue($task1);
        $scheduler->enqueue($task2);
        
        // First tick should execute first step of each task
        $scheduler->tick();
        $this->assertSame(['task1-step1', 'task2-step1'], $executed);
        
        // Second tick should execute second step of each task
        $scheduler->tick();
        $this->assertSame(['task1-step1', 'task2-step1', 'task1-step2', 'task2-step2'], $executed);
    }
    
    public function testTasksYieldControlDuringTick(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $order = [];
        
        $generator1 = (function () use (&$order) {
            $order[] = 'A1';
            yield;
            $order[] = 'A2';
        })();
        
        $generator2 = (function () use (&$order) {
            $order[] = 'B1';
            yield;
            $order[] = 'B2';
        })();
        
        $scheduler->enqueue($generator1);
        $scheduler->enqueue($generator2);
        
        $scheduler->tick();
        
        // Both tasks should have executed their first step
        $this->assertContains('A1', $order);
        $this->assertContains('B1', $order);
        $this->assertCount(2, $order);
    }
}
