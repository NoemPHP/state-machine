<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

final class ThrottleBehaviorTest extends TestCase
{
    public function testTaskExecutesAtMostOncePerThrottlePeriod(): void
    {
        $scheduler = new CoroutineScheduler();

        $executions = 0;
        $generator = (function () use (&$executions) {
            while (true) {
                $executions++; // Increment before yield
                yield;
            }
        })();

        $config = new AsyncConfig(throttle: 0.05, priority: Priority::LOW); // 50ms throttle, 1 step

        $scheduler->enqueue($generator, $config, function () {});

        // First tick - should execute
        $scheduler->tick();
        $this->assertEquals(1, $executions, 'First execution should succeed');

        // Immediate second tick - should be throttled
        $scheduler->tick();
        $this->assertEquals(1, $executions, 'Should not execute again within throttle period');

        // Wait for throttle to expire
        usleep(55000); // 55ms

        $scheduler->tick();
        $this->assertEquals(2, $executions, 'Should execute again after throttle period');
    }

    public function testThrottleLimitsExecutionRate(): void
    {
        $scheduler = new CoroutineScheduler();

        $executions = 0;
        $generator = (function () use (&$executions) {
            while (true) {
                $executions++; // Increment before yield
                yield;
            }
        })();

        $config = new AsyncConfig(throttle: 0.02, priority: Priority::LOW); // 20ms throttle, 1 step

        $scheduler->enqueue($generator, $config, function () {});

        // Rapid ticks over 100ms
        $startTime = microtime(true);
        while (microtime(true) - $startTime < 0.1) { // 100ms
            $scheduler->tick();
            usleep(5000); // 5ms between ticks
        }

        // With 20ms throttle, should execute at most ~5 times in 100ms
        // (100ms / 20ms = 5 executions)
        $this->assertLessThanOrEqual(6, $executions, 'Throttle should limit execution rate');
        $this->assertGreaterThanOrEqual(3, $executions, 'Should execute at least 3 times in 100ms');
    }

    public function testThrottleDoesNotPreventFirstExecution(): void
    {
        $scheduler = new CoroutineScheduler();

        $executed = false;
        $generator = (function () use (&$executed) {
            $executed = true; // Set before yield
            yield;
        })();

        $config = new AsyncConfig(throttle: 1.0, priority: Priority::LOW); // 1 second throttle, 1 step

        $scheduler->enqueue($generator, $config, function () {});

        // First tick should execute immediately
        $scheduler->tick();
        $this->assertTrue($executed, 'First execution should not be throttled');
    }

    public function testThrottleStateIndependentPerTask(): void
    {
        $scheduler = new CoroutineScheduler();

        $task1Executions = 0;
        $task2Executions = 0;

        $gen1 = (function () use (&$task1Executions) {
            while (true) {
                $task1Executions++; // Increment before yield
                yield;
            }
        })();

        $gen2 = (function () use (&$task2Executions) {
            while (true) {
                $task2Executions++; // Increment before yield
                yield;
            }
        })();

        $config1 = new AsyncConfig(throttle: 0.05, priority: Priority::LOW); // 50ms, 1 step
        $config2 = new AsyncConfig(throttle: 0.03, priority: Priority::LOW); // 30ms, 1 step

        $scheduler->enqueue($gen1, $config1, function () {});
        $scheduler->enqueue($gen2, $config2, function () {});

        // Both execute first time
        $scheduler->tick();
        $this->assertEquals(1, $task1Executions);
        $this->assertEquals(1, $task2Executions);

        // Wait 35ms - task2 can execute, task1 cannot
        usleep(35000);

        $scheduler->tick();
        $this->assertEquals(1, $task1Executions, 'Task1 still throttled');
        $this->assertEquals(2, $task2Executions, 'Task2 should execute again');

        // Wait another 35ms (total 70ms) - both can execute
        // task1: 70ms since first execution > 50ms throttle ✓
        // task2: 35ms since second execution > 30ms throttle ✓
        usleep(35000);

        $scheduler->tick();
        $this->assertEquals(2, $task1Executions, 'Task1 should execute now');
        $this->assertEquals(3, $task2Executions, 'Task2 should execute again');
    }
}
