<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler prevents duplicate task enqueueing for same generator
 */
#[Group('async'), Group('coroutine-scheduler')]
class PreventsDuplicateEnqueueTest extends TestCase
{
    public function testPreventsDuplicateEnqueue(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $generator = (function () {
            yield 'first';
            yield 'second';
        })();
        
        $task1 = $scheduler->enqueue($generator);
        $task2 = $scheduler->enqueue($generator);
        
        $this->assertSame($task1, $task2, 
            'Enqueueing the same generator twice should return the same task');
    }
    
    public function testReturnsExistingTaskWhenAlreadyEnqueued(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $generator = (function () {
            yield 'value';
        })();
        
        $firstTask = $scheduler->enqueue($generator);
        $secondTask = $scheduler->enqueue($generator);
        
        $this->assertSame($firstTask, $secondTask);
        $this->assertSame($firstTask, $scheduler->getTaskForCoroutine($generator));
    }
}
