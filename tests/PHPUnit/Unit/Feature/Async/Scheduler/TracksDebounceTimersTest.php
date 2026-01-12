<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\TestCase;

final class TracksDebounceTimersTest extends TestCase
{
    public function testSchedulerTracksDebounceTimersPerTask(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(debounce: 0.5);

        $task = $scheduler->enqueue($generator, $config, function () {
        });

        // Verify debounce time is tracked in task
        $this->assertNotNull($task->getDebounceTime());
        $this->assertIsFloat($task->getDebounceTime());
    }

    public function testDebounceTimerRecordsEnqueueTime(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(debounce: 0.5);

        $beforeEnqueue = microtime(true);
        $task = $scheduler->enqueue($generator, $config, function () {
        });
        $afterEnqueue = microtime(true);

        $enqueueTime = $task->getDebounceTime();

        $this->assertGreaterThanOrEqual($beforeEnqueue, $enqueueTime);
        $this->assertLessThanOrEqual($afterEnqueue, $enqueueTime);
    }

    public function testTaskWithoutDebounceHasNoTimer(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(); // No debounce

        $task = $scheduler->enqueue($generator, $config, function () {
        });

        // No timer should be set for tasks without debounce
        $this->assertNull($task->getDebounceTime());
    }
}
