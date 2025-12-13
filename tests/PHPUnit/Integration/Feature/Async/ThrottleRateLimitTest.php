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
 * Acceptance Criterion: Triggers within throttle period are queued or dropped
 * Intent: Prevents excessive execution during high-frequency triggers, enforcing rate limit
 */
#[Group('async'), Group('integration'), Group('throttle')]
class ThrottleRateLimitTest extends TestCase
{
    public function testTriggersWithinThrottlePeriodAreDropped(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $executions = 0;

        $throttledCallback = function (object $trigger) use (&$executions) {
            $executions++;
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

        // First trigger executes
        $region->trigger(new \stdClass());
        $this->assertEquals(1, $executions, 'First trigger should execute');

        // Rapid triggers within throttle period - should be throttled
        for ($i = 0; $i < 5; $i++) {
            usleep(10000); // 10ms between triggers (well within 100ms throttle)
            $region->trigger(new \stdClass());
        }

        $this->assertEquals(1, $executions, 'Triggers within throttle period should be dropped');
    }
}
