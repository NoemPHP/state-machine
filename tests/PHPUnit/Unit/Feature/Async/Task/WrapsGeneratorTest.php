<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Task wraps generator and tracks execution state
 */
#[Group('async'), Group('task-management')]
class WrapsGeneratorTest extends TestCase
{
    public function testWrapsGenerator(): void
    {
        $generator = (function () {
            yield 'first';
            yield 'second';
            return 'done';
        })();

        $task = new Task($generator);

        $this->assertInstanceOf(Task::class, $task);
        $this->assertFalse($task->isFinished(), 'Task should not be finished initially');
    }

    public function testTracksExecutionState(): void
    {
        $generator = (function () {
            yield 'value';
        })();

        $task = new Task($generator);

        // Initial state - not finished
        $this->assertFalse($task->isFinished());

        // After first run - still not finished (generator is on first yield)
        $task->run();
        $this->assertFalse($task->isFinished());

        // After second run - advances past yield, finishes
        $task->run();
        $this->assertTrue($task->isFinished(), 'Task should be finished after running past final yield');
    }

    public function testEncapsulatesGenerator(): void
    {
        $steps = [];

        $generator = (function () use (&$steps) {
            $steps[] = 'step1';
            yield;
            $steps[] = 'step2';
            yield;
        })();

        $task = new Task($generator);

        // Generator should not execute until task runs
        $this->assertEmpty($steps);

        // First run triggers first step
        $task->run();
        $this->assertSame(['step1'], $steps);

        // Second run triggers second step
        $task->run();
        $this->assertSame(['step1', 'step2'], $steps);
    }
}
