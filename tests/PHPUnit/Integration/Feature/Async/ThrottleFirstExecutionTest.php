<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: First throttled trigger executes immediately
 * Intent: Provides immediate response on initial trigger, applying throttle only to subsequent triggers
 */
#[Group('async'), Group('integration'), Group('throttle')]
class ThrottleFirstExecutionTest extends TestCase
{
    public function testFirstThrottledTriggerExecutesImmediately(): void
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
            ->onAction('active', $throttledCallback, $config);

        $region = $builder->build();

        // First trigger should execute immediately
        $region->trigger(new \stdClass());

        $this->assertEquals(1, $executions, 'First throttled trigger should execute immediately');
    }
}
