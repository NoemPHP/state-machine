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
 * Acceptance Criterion: Debounced callback executes only after trigger activity stops
 * Intent: Validates debounce behavior in real state machine scenario
 */
#[Group('async'), Group('integration')]
class DebouncedCallbackTest extends TestCase
{
    public function testDebouncedCallbackExecutesOnlyAfterTriggerActivityStops(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $executions = 0;
        $lastPayload = null;

        $debouncedCallback = function (object $trigger) use (&$executions, &$lastPayload) {
            $executions++;
            $lastPayload = $trigger->value ?? null;
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

        // First trigger - should enqueue but not execute due to debounce
        $trigger1 = new \stdClass();
        $trigger1->value = 'first';
        $region->trigger($trigger1);

        $this->assertEquals(0, $executions, 'Should not execute during debounce period');

        // Wait for debounce period to elapse
        usleep(150000); // 150ms > 100ms debounce

        // Trigger again to tick scheduler (debounced task should now execute)
        $trigger2 = new \stdClass();
        $trigger2->value = 'second';
        $region->trigger($trigger2);

        $this->assertEquals(1, $executions, 'Should execute after debounce period');
        $this->assertEquals('first', $lastPayload, 'Should use first trigger payload');
    }
}
