<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler enqueues generators as tasks
 */
#[Group('async'), Group('coroutine-scheduler')]
class EnqueuesGeneratorsTest extends TestCase
{
    public function testEnqueuesGenerators(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $generatorInstance = (function () {
            yield 1;
            yield 2;
        })();

        $task = $scheduler->enqueue($generatorInstance);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertTrue($scheduler->contains($generatorInstance),
            'Scheduler should contain the enqueued generator');
    }
    
    public function testEnqueueReturnsTaskForGenerator(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $generator = (function () {
            yield 'value';
        })();
        
        $task = $scheduler->enqueue($generator);
        
        $this->assertInstanceOf(Task::class, $task);
        $this->assertSame($task, $scheduler->getTaskForCoroutine($generator));
    }
}
