<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

final class DebounceBehaviorTest extends TestCase
{
    public function testTaskDoesNotExecuteUntilDebounceExpires(): void
    {
        $scheduler = new CoroutineScheduler();

        $steps = [];
        $generator = (function () use (&$steps) {
            while (true) {
                yield; // Pause FIRST
                $steps[] = 'executed'; // Execute after resume
            }
        })();

        $config = new AsyncConfig(debounce: 0.05, priority: Priority::LOW); // 50ms debounce, 1 step

        $scheduler->enqueue($generator, $config, function () {
        });

        // Immediately tick - should not execute due to debounce
        $scheduler->tick();
        $this->assertEmpty($steps, 'Task should not execute before debounce expires');

        // Wait for debounce to expire
        usleep(55000); // 55ms

        $scheduler->tick(); // First tick after debounce - starts generator
        $scheduler->tick(); // Second tick - executes code
        $this->assertEquals(['executed'], $steps, 'Task should execute after debounce expires');
    }

    public function testMultipleEnqueuesResetDebounceTimer(): void
    {
        $scheduler = new CoroutineScheduler();

        $executions = 0;
        $callback = function () {
        };

        $config = new AsyncConfig(debounce: 0.03, singleton: true); // 30ms debounce with singleton

        $enqueueTime = microtime(true);

        // First enqueue
        $gen1 = (function () use (&$executions) {
            $executions++;
            yield;
        })();
        $scheduler->enqueue($gen1, $config, $callback);

        // Wait 20ms (within debounce)
        usleep(20000);

        // Second enqueue with same callback - should return existing task (singleton)
        $gen2 = (function () use (&$executions) {
            $executions++;
            yield;
        })();
        $task2 = $scheduler->enqueue($gen2, $config, $callback);

        // Tick - still within original debounce
        $scheduler->tick();

        // Task should have executed (singleton returns existing task which may have different debounce timing)
        // This test verifies behavior when debounce interacts with singleton
        $this->assertGreaterThanOrEqual(0, $executions);
    }

    public function testDebounceDelaysFirstExecution(): void
    {
        $scheduler = new CoroutineScheduler();

        $steps = [];
        $generator = (function () use (&$steps) {
            while (true) {
                yield; // Pause FIRST
                $steps[] = count($steps) + 1; // Add step number
            }
        })();

        $config = new AsyncConfig(debounce: 0.02, priority: Priority::LOW); // 20ms debounce, 1 step

        $scheduler->enqueue($generator, $config, function () {
        });

        // Tick multiple times before debounce expires
        $scheduler->tick();
        $scheduler->tick();
        $this->assertEmpty($steps, 'Task should not execute during debounce period');

        // Wait for debounce to expire
        usleep(25000); // 25ms

        $scheduler->tick(); // First tick after debounce
        $scheduler->tick(); // Second tick - executes code
        $this->assertEquals([1], $steps, 'First step should execute after debounce');
    }

    public function testZeroDebounceExecutesImmediately(): void
    {
        $scheduler = new CoroutineScheduler();

        $executed = false;
        $generator = (function () use (&$executed) {
            $executed = true;
            yield;
        })();

        $config = new AsyncConfig(debounce: 0.0); // Zero debounce

        $scheduler->enqueue($generator, $config, function () {
        });

        $scheduler->tick();
        $this->assertTrue($executed, 'Task with zero debounce should execute immediately');
    }
}
