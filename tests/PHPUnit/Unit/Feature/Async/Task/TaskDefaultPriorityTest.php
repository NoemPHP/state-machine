<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Task;

use Noem\State\Feature\Async\Priority;
use Noem\State\Feature\Async\Task;
use PHPUnit\Framework\TestCase;

final class TaskDefaultPriorityTest extends TestCase
{
    public function testTaskDefaultsToNormalPriorityWhenNotSpecified(): void
    {
        $generator = (function () {
            yield 1;
        })();

        $task = new Task($generator);

        $this->assertSame(Priority::NORMAL, $task->priority);
    }

    public function testDefaultPriorityValueIsCorrect(): void
    {
        $generator = (function () {
            yield 1;
        })();

        $task = new Task($generator);

        $this->assertEquals(5, $task->priority->value);
        $this->assertEquals(Priority::NORMAL->value, $task->priority->value);
    }
}
