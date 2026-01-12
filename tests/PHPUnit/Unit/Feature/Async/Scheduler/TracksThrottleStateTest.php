<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\TestCase;

final class TracksThrottleStateTest extends TestCase
{
    public function testSchedulerTracksThrottleStatePerTask(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(throttle: 1.0);

        $task = $scheduler->enqueue($generator, $config, function () {
        });

        // Throttle state should exist for task (initially null before first execution)
        $this->assertNull($task->getLastExecutionTime());
    }

    public function testThrottleStateUpdatesAfterExecution(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(throttle: 1.0);

        $task = $scheduler->enqueue($generator, $config, function () {
        });

        // Initial state should not be set
        $this->assertNull($task->getLastExecutionTime());

        // After tick, throttle state should be recorded
        $beforeTick = microtime(true);
        $scheduler->tick();
        $afterTick = microtime(true);

        $executionTime = $task->getLastExecutionTime();
        $this->assertNotNull($executionTime);
        $this->assertIsFloat($executionTime);
        $this->assertGreaterThanOrEqual($beforeTick, $executionTime);
        $this->assertLessThanOrEqual($afterTick, $executionTime);
    }

    public function testTaskWithoutThrottleHasNoState(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            yield 1;
        })();

        $config = new AsyncConfig(); // No throttle

        $task = $scheduler->enqueue($generator, $config, function () {
        });
        $scheduler->tick();

        // No state should be tracked for tasks without throttle
        $this->assertNull($task->getLastExecutionTime());
    }
}
