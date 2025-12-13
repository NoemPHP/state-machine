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
 * Acceptance Criterion: Only most recent trigger payload is used after debounce period
 * Intent: Executes with final trigger data, discarding intermediate triggers during debounce
 */
#[Group('async'), Group('integration'), Group('debounce')]
class DebounceFinalPayloadTest extends TestCase
{
    public function testOnlyMostRecentPayloadUsedAfterDebounce(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $receivedPayload = null;

        $debouncedCallback = function (object $trigger) use (&$receivedPayload) {
            $receivedPayload = $trigger->value ?? null;
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

        // Send multiple triggers during debounce period
        $trigger1 = new \stdClass();
        $trigger1->value = 'first';
        $region->trigger($trigger1);

        usleep(30000); // 30ms

        $trigger2 = new \stdClass();
        $trigger2->value = 'second';
        $region->trigger($trigger2);

        usleep(30000); // 30ms

        $trigger3 = new \stdClass();
        $trigger3->value = 'third';
        $region->trigger($trigger3);

        $this->assertNull($receivedPayload, 'Should not have executed yet');

        // Wait for debounce period to elapse from last trigger
        usleep(150000);

        // Tick to execute
        $region->trigger(new \stdClass());

        $this->assertEquals('third', $receivedPayload, 'Should receive only the final payload');
    }
}
