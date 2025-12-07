<?php

declare(strict_types=1);

namespace Noem\State\Test\E2E;

use Noem\State\Feature\Async\CoroutineScheduler;
use Noem\State\Region;

/**
 * Base test case for machines using AsyncFeature.
 *
 * Provides utilities for testing cooperative multitasking and task scheduling.
 */
abstract class AsyncMachineTestCase extends ApplicationTestCase
{
    /**
     * Tick the region's scheduler until a condition is met or max ticks reached.
     *
     * @param Region $region The region to tick
     * @param callable $condition Callback returning bool when condition met
     * @param int $maxTicks Maximum number of ticks before giving up
     * @throws \RuntimeException If max ticks exceeded
     */
    protected function tickUntil(Region $region, callable $condition, int $maxTicks = 100): void
    {
        $ticks = 0;

        while (!$condition() && $ticks < $maxTicks) {
            $region->trigger(new \stdClass());
            $ticks++;
        }

        if ($ticks >= $maxTicks) {
            throw new \RuntimeException(
                "tickUntil exceeded {$maxTicks} ticks without condition being met"
            );
        }
    }

    /**
     * Execute the region for a fixed number of ticks.
     *
     * Useful for testing concurrent operations that need time to progress.
     *
     * @param Region $region The region to tick
     * @param int $ticks Number of ticks to execute
     */
    protected function tickN(Region $region, int $ticks): void
    {
        for ($i = 0; $i < $ticks; $i++) {
            $region->trigger(new \stdClass());
        }
    }

    /**
     * Get the scheduler from a region.
     *
     * @param Region $region
     * @return CoroutineScheduler
     */
    protected function getScheduler(Region $region): CoroutineScheduler
    {
        // Access the scheduler through the region's internal state
        // This assumes AsyncFeature stores it in a predictable location
        $reflection = new \ReflectionClass($region);
        $property = $reflection->getProperty('chainMail');
        $property->setAccessible(true);
        $chainMail = $property->getValue($region);

        return $chainMail->get(CoroutineScheduler::class);
    }

    /**
     * Count active tasks in the scheduler.
     *
     * @param Region $region
     * @return int Number of active tasks
     */
    protected function countActiveTasks(Region $region): int
    {
        $scheduler = $this->getScheduler($region);

        // Use reflection to access task count
        $reflection = new \ReflectionClass($scheduler);
        $property = $reflection->getProperty('tasks');
        $property->setAccessible(true);
        $tasks = $property->getValue($scheduler);

        return count($tasks);
    }

    /**
     * Assert that a region has active async tasks.
     *
     * @param Region $region
     * @param string $message
     */
    protected function assertHasActiveTasks(Region $region, string $message = ''): void
    {
        $count = $this->countActiveTasks($region);
        $this->assertGreaterThan(
            0,
            $count,
            $message ?: "Expected region to have active tasks, but found {$count}"
        );
    }

    /**
     * Assert that a region has no active async tasks.
     *
     * @param Region $region
     * @param string $message
     */
    protected function assertNoActiveTasks(Region $region, string $message = ''): void
    {
        $count = $this->countActiveTasks($region);
        $this->assertSame(
            0,
            $count,
            $message ?: "Expected region to have no active tasks, but found {$count}"
        );
    }
}
