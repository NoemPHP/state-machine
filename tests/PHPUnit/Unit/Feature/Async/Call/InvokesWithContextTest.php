<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Call;

use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Call objects invoke callback with task and scheduler
 */
#[Group('async'), Group('call-helpers')]
class InvokesWithContextTest extends TestCase
{
    public function testInvokesWithContext(): void
    {
        $receivedTask = null;
        $receivedScheduler = null;
        $callbackInvoked = false;

        $call = new Call(function (Task $task, CoroutineScheduler $scheduler) use (&$receivedTask, &$receivedScheduler, &$callbackInvoked) {
            $callbackInvoked = true;
            $receivedTask = $task;
            $receivedScheduler = $scheduler;
        });

        $scheduler = new CoroutineScheduler();
        $generator = (function () {
            yield 'value';
        })();

        $task = $scheduler->enqueue($generator);

        // Invoke the call manually with task and scheduler
        $call($task, $scheduler);

        $this->assertTrue($callbackInvoked, 'Callback should be invoked');
        $this->assertSame($task, $receivedTask, 'Call should receive the task');
        $this->assertSame($scheduler, $receivedScheduler, 'Call should receive the scheduler');
    }

    public function testCallbackReceivesCorrectParameters(): void
    {
        $parameterCount = null;
        $firstParamType = null;
        $secondParamType = null;

        $call = new Call(function (...$params) use (&$parameterCount, &$firstParamType, &$secondParamType) {
            $parameterCount = count($params);
            $firstParamType = get_class($params[0]);
            $secondParamType = get_class($params[1]);
        });

        $scheduler = new CoroutineScheduler();
        $generator = (function () {
            yield;
        })();
        $task = $scheduler->enqueue($generator);

        $call($task, $scheduler);

        $this->assertSame(2, $parameterCount);
        $this->assertSame(Task::class, $firstParamType);
        $this->assertSame(CoroutineScheduler::class, $secondParamType);
    }
}
