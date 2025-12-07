<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: CoroutineScheduler stores last yielded value per task
 */
#[Group('async'), Group('coroutine-scheduler')]
class StoresLastYieldedValueTest extends TestCase
{
    public function testStoresLastYieldedValue(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 'first';
            yield 'second';
            yield 'third';
        })();

        $task = $scheduler->enqueue($generator);

        // Initially no value
        $this->assertNull($scheduler->getLastYielded($task));

        // After first tick, should have first yielded value
        $scheduler->tick();
        $this->assertSame('first', $scheduler->getLastYielded($task));

        // After second tick, should have second yielded value
        $scheduler->tick();
        $this->assertSame('second', $scheduler->getLastYielded($task));

        // After third tick, should have third yielded value
        $scheduler->tick();
        $this->assertSame('third', $scheduler->getLastYielded($task));
    }

    public function testStoresValuePerTask(): void
    {
        $scheduler = new CoroutineScheduler();

        $gen1 = (function () {
            yield 'A';
            yield 'B';
        })();

        $gen2 = (function () {
            yield 'X';
            yield 'Y';
        })();

        $task1 = $scheduler->enqueue($gen1);
        $task2 = $scheduler->enqueue($gen2);

        $scheduler->tick();

        $this->assertSame('A', $scheduler->getLastYielded($task1));
        $this->assertSame('X', $scheduler->getLastYielded($task2));
    }
}
