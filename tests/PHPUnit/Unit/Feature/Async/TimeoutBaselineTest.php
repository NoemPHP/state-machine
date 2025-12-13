<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Timeout is measured from task enqueue time
 * Intent: Establishes baseline for timeout measurement, including debounce and throttle delays
 */
#[Group('async'), Group('unit'), Group('timeout')]
class TimeoutBaselineTest extends TestCase
{
    public function testTimeoutMeasuredFromTaskEnqueueTime(): void
    {
        $scheduler = new CoroutineScheduler();
        $config = new AsyncConfig(timeout: 1.0);

        $generator = (function () {
            yield 'test';
        })();

        $beforeEnqueue = microtime(true);
        $task = $scheduler->enqueue($generator, $config);
        $afterEnqueue = microtime(true);

        // Verify timeout baseline was set at enqueue time (stored in debounceTime)
        $baselineTime = $task->getDebounceTime();
        $this->assertNotNull($baselineTime, 'Timeout baseline should be set on enqueue');
        $this->assertGreaterThanOrEqual($beforeEnqueue, $baselineTime);
        $this->assertLessThanOrEqual($afterEnqueue, $baselineTime);
    }
}
