<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Throttled callback respects minimum interval between executions
 * Intent: Validates throttle rate limiting in rapid trigger scenario
 */
#[Group('async'), Group('integration')]
class ThrottledCallbackTest extends TestCase
{
    public function testThrottledCallbackRespectsMinimumIntervalBetweenExecutions(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $executions = 0;
        $timestamps = [];

        $throttledCallback = function (object $trigger) use (&$executions, &$timestamps) {
            $executions++;
            $timestamps[] = microtime(true);
            yield;
        };

        $config = new AsyncConfig(throttle: 0.1);

        $builder
            ->setStates('active')
            ->addBuildStep(new AddCallback(
                type: AsyncCallbackType::get(),
                event: 'action',
                state: 'active',
                callback: $throttledCallback,
                metadata: $config
            ));

        $region = $builder->build();

        // First trigger - should execute immediately
        $region->trigger(new \stdClass());
        $this->assertEquals(1, $executions, 'First trigger should execute immediately');

        // Immediate second trigger - should be throttled
        $region->trigger(new \stdClass());
        $this->assertEquals(1, $executions, 'Second trigger should be throttled');

        // Wait for throttle period to elapse
        usleep(150000); // 150ms

        // Third trigger - should execute after throttle period
        $region->trigger(new \stdClass());
        $this->assertEquals(2, $executions, 'Should execute after throttle period');

        // Verify minimum interval between executions
        if (count($timestamps) >= 2) {
            $interval = $timestamps[1] - $timestamps[0];
            $this->assertGreaterThanOrEqual(0.1, $interval, 'Should respect minimum interval');
        }
    }
}
