<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Task reports completion status via isFinished
 */
#[Group('async'), Group('task-management')]
class ReportsCompletionStatusTest extends TestCase
{
    public function testReportsCompletionStatus(): void
    {
        $generator = (function () {
            yield 'value';
        })();

        $task = new Task($generator);

        // Initially not finished
        $this->assertFalse($task->isFinished(), 'Task should not be finished initially');

        // After first run, still not finished (on the yield point)
        $task->run();
        $this->assertFalse($task->isFinished(), 'Task should not be finished after first yield');

        // After second run, advances past yield and finishes
        $task->run();
        $this->assertTrue($task->isFinished(), 'Task should be finished after running past final yield');
    }

    public function testFinishedAfterAllYields(): void
    {
        $generator = (function () {
            yield 'a';
            yield 'b';
            yield 'c';
        })();

        $task = new Task($generator);

        $this->assertFalse($task->isFinished());
        $task->run(); // First yield
        $this->assertFalse($task->isFinished());
        $task->run(); // Second yield
        $this->assertFalse($task->isFinished());
        $task->run(); // Third yield
        $this->assertFalse($task->isFinished());
        $task->run(); // Past all yields
        $this->assertTrue($task->isFinished());
    }

    public function testImmediatelyFinishedForEmptyGenerator(): void
    {
        $generator = (function () {
            return 'done';
            yield; // Never reached
        })();

        $task = new Task($generator);

        // Generator with return before any yield is immediately finished
        $this->assertTrue(
            $task->isFinished(),
            'Generator that returns before yielding should be immediately finished'
        );
    }

    public function testRemainsFinishedAfterCompletion(): void
    {
        $generator = (function () {
            yield;
        })();

        $task = new Task($generator);

        $task->run();
        $task->run();

        $this->assertTrue($task->isFinished());

        // Should remain finished
        $this->assertTrue($task->isFinished());
        $this->assertTrue($task->isFinished());
    }
}
