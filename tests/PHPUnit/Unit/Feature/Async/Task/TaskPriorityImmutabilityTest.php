<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Priority;
use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\TestCase;

final class TaskPriorityImmutabilityTest extends TestCase
{
    public function testPriorityCannotBeModifiedAfterCreation(): void
    {
        $generator = (function () {
            yield 1;
        })();

        $task = new Task($generator, Priority::HIGH);

        // Attempt to modify readonly property should cause error
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Cannot modify readonly property');

        $task->priority = Priority::LOW;
    }

    public function testPriorityRemainsConstantThroughoutExecution(): void
    {
        $generator = (function () {
            yield 1;
            yield 2;
            yield 3;
        })();

        $task = new Task($generator, Priority::HIGH);

        $this->assertSame(Priority::HIGH, $task->priority);

        $task->run();
        $this->assertSame(Priority::HIGH, $task->priority);

        $task->run();
        $this->assertSame(Priority::HIGH, $task->priority);

        $task->run();
        $this->assertSame(Priority::HIGH, $task->priority);
    }
}
