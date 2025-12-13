<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Priority;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Tasks exceeding timeout duration are cancelled
 *
 * Intent: Prevents runaway tasks from consuming resources indefinitely, enforcing execution time limits
 */
#[Group('async'), Group('timeout'), Group('integration')]
class TimeoutCancellationTest extends TestCase
{
    public function testTasksExceedingTimeoutAreCancelled(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $executionCount = 0;
        $cancelled = false;

        $callback = function (object $trigger) use (&$executionCount, &$cancelled) {
            while (!$cancelled) {
                $executionCount++;
                yield;
            }
        };

        // Register async callback with 50ms timeout
        $config = new AsyncConfig(
            timeout: 0.05, // 50ms timeout
            priority: Priority::NORMAL
        );

        $builder
            ->setStates('idle')
            ->addBuildStep(new AddCallback(
                type: AsyncCallbackType::get(),
                event: 'action',
                state: 'idle',
                callback: $callback,
                metadata: $config
            ));

        $region = $builder->build();

        // Trigger - task starts
        $region->trigger(new \stdClass());

        $this->assertGreaterThan(0, $executionCount, 'Task should execute initially');
        $countBeforeTimeout = $executionCount;

        // Wait for timeout to expire
        usleep(60000); // 60ms - beyond 50ms timeout

        // Trigger again - scheduler tick should detect timeout and cancel task
        $region->trigger(new \stdClass());

        // Task should be cancelled - no further execution
        // (cancelled tasks don't increment counter)
        $this->assertEquals($countBeforeTimeout, $executionCount, 'Task should be cancelled after timeout');
    }

    public function testTaskWithinTimeoutContinuesExecution(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $steps = [];

        $callback = function (object $trigger) use (&$steps) {
            $steps[] = 'step1';
            yield;
            $steps[] = 'step2';
            yield;
            $steps[] = 'step3';
        };

        // Register async callback with generous timeout
        $config = new AsyncConfig(
            timeout: 10.0, // 10 second timeout - plenty of time
            priority: Priority::LOW // 1 step per tick
        );

        $builder
            ->setStates('idle')
            ->addBuildStep(new AddCallback(
                type: AsyncCallbackType::get(),
                event: 'action',
                state: 'idle',
                callback: $callback,
                metadata: $config
            ));

        $region = $builder->build();

        // First trigger - step1
        $region->trigger(new \stdClass());
        $this->assertSame(['step1'], $steps);

        // Second trigger - step2
        $region->trigger(new \stdClass());
        $this->assertSame(['step1', 'step2'], $steps);

        // Third trigger - step3
        $region->trigger(new \stdClass());
        $this->assertSame(['step1', 'step2', 'step3'], $steps);

        // Task completes within timeout - no cancellation
    }
}
