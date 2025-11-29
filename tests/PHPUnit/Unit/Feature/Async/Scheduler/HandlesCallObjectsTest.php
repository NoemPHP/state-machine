<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler handles Call objects during task execution
 */
#[Group('async'), Group('coroutine-scheduler')]
class HandlesCallObjectsTest extends TestCase
{
    public function testHandlesCallObjects(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $callInvoked = false;
        $receivedTask = null;
        $receivedScheduler = null;
        
        $call = new Call(function (Task $task, CoroutineScheduler $sched) use (&$callInvoked, &$receivedTask, &$receivedScheduler) {
            $callInvoked = true;
            $receivedTask = $task;
            $receivedScheduler = $sched;
        });
        
        $generator = (function () use ($call) {
            yield $call;
        })();
        
        $task = $scheduler->enqueue($generator);
        
        $this->assertFalse($callInvoked);
        
        // Tick should execute the task and invoke the Call object
        $scheduler->tick();
        
        $this->assertTrue($callInvoked, 'Call object should be invoked during tick');
        $this->assertSame($task, $receivedTask, 'Call should receive the current task');
        $this->assertSame($scheduler, $receivedScheduler, 'Call should receive the scheduler');
    }
    
    public function testCallObjectDoesNotStoreAsLastYielded(): void
    {
        $scheduler = new CoroutineScheduler();
        
        $call = new Call(function (Task $task, CoroutineScheduler $scheduler) {
            // No-op call
        });
        
        $generator = (function () use ($call) {
            yield 'before-call';
            yield $call;
            yield 'after-call';
        })();
        
        $task = $scheduler->enqueue($generator);
        
        $scheduler->tick();
        $this->assertSame('before-call', $scheduler->getLastYielded($task));
        
        // When Call is yielded, it should not update lastYielded
        $scheduler->tick();
        $this->assertSame('before-call', $scheduler->getLastYielded($task),
            'Call objects should not be stored as last yielded value');
        
        $scheduler->tick();
        $this->assertSame('after-call', $scheduler->getLastYielded($task));
    }
}
