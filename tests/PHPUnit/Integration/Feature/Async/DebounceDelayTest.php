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
 * Acceptance Criterion: Debounced task does not execute until debounce period elapses
 * Intent: Prevents premature execution during rapid trigger bursts, waiting for quiet period
 */
#[Group('async'), Group('integration'), Group('debounce')]
class DebounceDelayTest extends TestCase
{
    public function testDebouncedTaskDoesNotExecuteUntilDebounceElapses(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $executionCount = 0;
        $callback = function (object $trigger) use (&$executionCount) {
            while (true) {
                $executionCount++;
                yield;
            }
        };

        $config = new AsyncConfig(debounce: 0.1, priority: \Noem\State\Feature\Async\Priority::LOW); // 100ms debounce, 1 step per tick

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
        $event = new \stdClass();

        // Trigger immediately - should not execute due to debounce
        $region->trigger($event);
        $this->assertEquals(0, $executionCount, 'Task should not execute during debounce period');

        // Wait for debounce to elapse
        usleep(150000); // 150ms

        // Trigger again - should execute now
        $region->trigger($event);
        $this->assertEquals(1, $executionCount, 'Task should execute after debounce period');
    }
}
