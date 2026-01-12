<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async\Scheduler;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Feature\Async\Priority;
use PHPUnit\Framework\TestCase;

final class PriorityBasedTickBudgetTest extends TestCase
{
    public function testHighPriorityTaskAdvances10StepsPerTick(): void
    {
        $scheduler = new CoroutineScheduler();

        $steps = 0;
        $generator = (function () use (&$steps) {
            while (true) {
                $steps++;
                yield $steps;
            }
        })();

        $config = new AsyncConfig(priority: Priority::HIGH);

        $scheduler->enqueue($generator, $config, function () {
        });
        $scheduler->tick();

        // HIGH priority = 10 steps per tick
        $this->assertEquals(10, $steps);
    }

    public function testNormalPriorityTaskAdvances5StepsPerTick(): void
    {
        $scheduler = new CoroutineScheduler();

        $steps = 0;
        $generator = (function () use (&$steps) {
            while (true) {
                $steps++;
                yield $steps;
            }
        })();

        $config = new AsyncConfig(priority: Priority::NORMAL);

        $scheduler->enqueue($generator, $config, function () {
        });
        $scheduler->tick();

        // NORMAL priority = 5 steps per tick
        $this->assertEquals(5, $steps);
    }

    public function testLowPriorityTaskAdvances1StepPerTick(): void
    {
        $scheduler = new CoroutineScheduler();

        $steps = 0;
        $generator = (function () use (&$steps) {
            while (true) {
                $steps++;
                yield $steps;
            }
        })();

        $config = new AsyncConfig(priority: Priority::LOW);

        $scheduler->enqueue($generator, $config, function () {
        });
        $scheduler->tick();

        // LOW priority = 1 step per tick
        $this->assertEquals(1, $steps);
    }

    public function testMixedPriorityTasksAdvanceProportionally(): void
    {
        $scheduler = new CoroutineScheduler();

        $lowSteps = 0;
        $normalSteps = 0;
        $highSteps = 0;

        $lowGen = (function () use (&$lowSteps) {
            while (true) {
                $lowSteps++;
                yield;
            }
        })();

        $normalGen = (function () use (&$normalSteps) {
            while (true) {
                $normalSteps++;
                yield;
            }
        })();

        $highGen = (function () use (&$highSteps) {
            while (true) {
                $highSteps++;
                yield;
            }
        })();

        $scheduler->enqueue($lowGen, new AsyncConfig(priority: Priority::LOW), function () {
        });
        $scheduler->enqueue($normalGen, new AsyncConfig(priority: Priority::NORMAL), function () {
        });
        $scheduler->enqueue($highGen, new AsyncConfig(priority: Priority::HIGH), function () {
        });

        $scheduler->tick();

        // Verify proportional advancement: LOW=1, NORMAL=5, HIGH=10
        $this->assertEquals(1, $lowSteps);
        $this->assertEquals(5, $normalSteps);
        $this->assertEquals(10, $highSteps);
    }
}
