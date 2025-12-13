<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\CoroutineScheduler;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Scheduler tracks last execution time for throttling
 * Intent: Records execution timestamps to enforce minimum interval between runs
 */
#[Group('async'), Group('unit'), Group('throttle')]
class ThrottleTimestampTrackingTest extends TestCase
{
    public function testSchedulerTracksLastExecutionTimeForThrottling(): void
    {
        $scheduler = new CoroutineScheduler();
        $config = new AsyncConfig(throttle: 1.0);

        $generator = (function () {
            yield 'test';
        })();

        $task = $scheduler->enqueue($generator, $config);

        // Execute once to trigger throttle tracking
        $scheduler->tick();

        // Verify throttle timestamp was recorded after execution
        $lastExecution = $task->getLastExecutionTime();
        $this->assertNotNull($lastExecution, 'Throttle timestamp should be recorded after execution');
        $this->assertIsFloat($lastExecution);
        $this->assertGreaterThan(0, $lastExecution);
    }
}
