<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\TestCase;

final class TimeoutCancellationTest extends TestCase
{
    public function testTasksExceedingTimeoutAreCancelled(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            while (true) {
                yield;
            }
        })();

        $config = new AsyncConfig(timeout: 0.01); // 10ms timeout

        $task = $scheduler->enqueue($generator, $config, function () {
        });

        $this->assertFalse($task->isFinished());

        // Wait for timeout to expire
        usleep(15000); // 15ms

        $scheduler->tick();

        // Task should be cancelled
        $this->assertTrue($task->isCancelled());
    }

    public function testTimeoutMeasuredFromEnqueueTime(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            while (true) {
                yield;
            }
        })();

        $config = new AsyncConfig(timeout: 0.05); // 50ms timeout

        $enqueueTime = microtime(true);
        $task = $scheduler->enqueue($generator, $config, function () {
        });

        // Wait 30ms (within timeout)
        usleep(30000);

        $scheduler->tick();

        // Task should still exist (not timed out)
        $this->assertFalse($task->isCancelled());

        // Wait until timeout expires
        $elapsed = microtime(true) - $enqueueTime;
        if ($elapsed < 0.05) {
            usleep((int)(($config->timeout - $elapsed) * 1000000) + 5000);
        }

        $scheduler->tick();

        // Now should be cancelled
        $this->assertTrue($task->isCancelled());
    }

    public function testSchedulerChecksTimeoutOnEachTick(): void
    {
        $scheduler = new CoroutineScheduler();

        $generator = (function () {
            while (true) {
                yield;
            }
        })();

        $config = new AsyncConfig(timeout: 0.02); // 20ms timeout

        $task = $scheduler->enqueue($generator, $config, function () {
        });

        // First tick - within timeout
        $scheduler->tick();

        $this->assertFalse($task->isFinished());

        // Verify task is still tracked (not cancelled)
        $this->assertFalse($task->isCancelled());

        // Wait for timeout
        usleep(25000); // 25ms

        // Second tick - timeout should be detected
        $scheduler->tick();

        // Verify task was cancelled
        $this->assertTrue($task->isCancelled());
    }

    public function testTaskWithoutTimeoutNeverCancels(): void
    {
        $scheduler = new CoroutineScheduler();

        $ticks = 0;
        $generator = (function () use (&$ticks) {
            while (true) {
                $ticks++;
                yield;
            }
        })();

        $config = new AsyncConfig(); // No timeout

        $task = $scheduler->enqueue($generator, $config, function () {
        });

        // Tick many times
        for ($i = 0; $i < 100; $i++) {
            $scheduler->tick();
        }

        // Task should still be running
        $this->assertFalse($task->isFinished());
        $this->assertGreaterThan(50, $ticks); // Should have progressed
    }
}
