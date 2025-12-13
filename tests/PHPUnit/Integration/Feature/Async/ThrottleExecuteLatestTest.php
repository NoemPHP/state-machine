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
 * Acceptance Criterion: After throttle period elapses, most recent trigger executes
 * Intent: Processes latest trigger after rate limit expires, discarding intermediate triggers
 */
#[Group('async'), Group('integration'), Group('throttle')]
class ThrottleExecuteLatestTest extends TestCase
{
    public function testAfterThrottlePeriodMostRecentTriggerExecutes(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $executions = 0;
        $lastPayload = null;

        $throttledCallback = function (object $trigger) use (&$executions, &$lastPayload) {
            $executions++;
            $lastPayload = $trigger->value ?? null;
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

        // First trigger
        $trigger1 = new \stdClass();
        $trigger1->value = 'first';
        $region->trigger($trigger1);
        $this->assertEquals(1, $executions, 'First trigger should execute');
        $this->assertEquals('first', $lastPayload, 'Should receive first payload');

        // Multiple triggers within throttle period
        usleep(30000);
        $trigger2 = new \stdClass();
        $trigger2->value = 'second';
        $region->trigger($trigger2);

        usleep(30000);
        $trigger3 = new \stdClass();
        $trigger3->value = 'third';
        $region->trigger($trigger3);

        $this->assertEquals(1, $executions, 'Should still be throttled');

        // Wait for throttle period to elapse
        usleep(100000);

        // Next trigger should execute with most recent payload
        $trigger4 = new \stdClass();
        $trigger4->value = 'fourth';
        $region->trigger($trigger4);

        $this->assertEquals(2, $executions, 'Should execute after throttle period');
        $this->assertEquals('fourth', $lastPayload, 'Should use most recent trigger payload');
    }
}
