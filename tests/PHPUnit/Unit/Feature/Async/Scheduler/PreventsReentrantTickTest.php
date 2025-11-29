<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler prevents reentrant tick execution
 */
#[Group('async'), Group('coroutine-scheduler')]
class PreventsReentrantTickTest extends TestCase
{
    public function testPreventsReentrantTick(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $tickCount = 0;
        
        $generator = (function () use ($scheduler, &$tickCount) {
            $tickCount++;
            // Try to trigger a reentrant tick from within a task
            $scheduler->tick();
            yield 'value';
        })();
        
        $scheduler->enqueue($generator);
        
        // Execute first tick
        $scheduler->tick();
        
        // Only one tick should have executed (reentrant tick should be prevented)
        $this->assertSame(1, $tickCount, 
            'Reentrant tick should be prevented, task should execute only once');
    }
    
    public function testNestedTickIsIgnored(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $executionOrder = [];
        
        $gen1 = (function () use ($scheduler, &$executionOrder) {
            $executionOrder[] = 'task1-start';
            // Attempt reentrant tick
            $scheduler->tick();
            $executionOrder[] = 'task1-afterTick';
            yield;
            $executionOrder[] = 'task1-step2';
        })();
        
        $gen2 = (function () use (&$executionOrder) {
            $executionOrder[] = 'task2-step1';
            yield;
        })();
        
        $scheduler->enqueue($gen1);
        $scheduler->enqueue($gen2);
        
        $scheduler->tick();
        
        // Reentrant tick should be ignored, task2 should not execute during task1
        $this->assertSame(
            ['task1-start', 'task1-afterTick', 'task2-step1'],
            $executionOrder,
            'Reentrant tick should be ignored, maintaining execution order'
        );
    }
}
