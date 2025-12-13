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
 * Acceptance Criterion: Triggering during debounce period resets the timer
 * Intent: Extends quiet period on each trigger, ensuring execution only after final trigger
 */
#[Group('async'), Group('integration'), Group('debounce')]
class DebounceTimerResetTest extends TestCase
{
    public function testTriggeringDuringDebounceResetsTimer(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $executions = 0;

        $debouncedCallback = function (object $trigger) use (&$executions) {
            $executions++;
            yield;
        };

        $config = new AsyncConfig(debounce: 0.1);

        $builder
            ->setStates('active')
            ->addBuildStep(new AddCallback(
                type: AsyncCallbackType::get(),
                event: 'action',
                state: 'active',
                callback: $debouncedCallback,
                metadata: $config
            ));

        $region = $builder->build();

        // First trigger
        $region->trigger(new \stdClass());
        $this->assertEquals(0, $executions, 'Should not execute immediately');

        // Wait 50ms (half of debounce period)
        usleep(50000);

        // Second trigger during debounce - should reset timer
        $region->trigger(new \stdClass());
        $this->assertEquals(0, $executions, 'Should still not execute - timer reset');

        // Wait another 50ms (only 50ms since last trigger, still within debounce)
        usleep(50000);

        // Should still not execute
        $region->trigger(new \stdClass());
        $this->assertEquals(0, $executions, 'Timer was reset, needs full 100ms from last trigger');

        // Wait full debounce period from last trigger
        usleep(150000);

        // Tick to execute debounced task
        $region->trigger(new \stdClass());
        $this->assertEquals(1, $executions, 'Should execute after full debounce period');
    }
}
