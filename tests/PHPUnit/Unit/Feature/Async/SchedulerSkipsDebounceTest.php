<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Scheduler skips debounced tasks until period elapses
 * Intent: Prevents execution during debounce window, implementing delayed execution semantics
 */
#[Group('async'), Group('unit'), Group('debounce')]
class SchedulerSkipsDebounceTest extends TestCase
{
    public function testSchedulerSkipsDebouncedTasksUntilPeriodElapses(): void
    {
        $scheduler = new CoroutineScheduler();
        $steps = [];
        $config = new AsyncConfig(debounce: 0.1, priority: \Noem\State\Feature\Async\Priority::LOW); // 100ms debounce, 1 step per tick

        $generator = (function () use (&$steps) {
            while (true) {
                yield; // Pause FIRST (generator pattern)
                $steps[] = 'executed'; // Code executes AFTER yield
            }
        })();

        $scheduler->enqueue($generator, $config, function () {});

        // Tick immediately - should be skipped due to debounce
        $scheduler->tick();
        $this->assertEmpty($steps, 'Task should not execute during debounce period');

        // Wait for debounce period to elapse
        usleep(150000); // 150ms

        // First tick after debounce - starts generator, hits yield
        $scheduler->tick();
        $this->assertEmpty($steps, 'First tick just starts generator');

        // Second tick - resumes generator, executes code
        $scheduler->tick();
        $this->assertEquals(['executed'], $steps, 'Task should execute after debounce period elapses');
    }
}
