<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Priority;
use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\TestCase;

final class TaskPriorityStorageTest extends TestCase
{
    public function testTaskStoresPriorityAsReadonlyProperty(): void
    {
        $generator = (function () {
            yield 1;
        })();

        $task = new Task($generator, Priority::HIGH);

        $this->assertSame(Priority::HIGH, $task->priority);
        $this->assertObjectHasProperty('priority', $task);
    }

    public function testPriorityIsAccessible(): void
    {
        $generator = (function () {
            yield 1;
        })();

        $task = new Task($generator, Priority::LOW);

        $this->assertEquals(Priority::LOW, $task->priority);
        $this->assertInstanceOf(Priority::class, $task->priority);
    }
}
