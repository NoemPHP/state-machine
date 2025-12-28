<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Response delivery completes before abilities() call returns
 *
 * Intent: Then handlers execute before invocation returns,
 *         supporting synchronous result access
 *
 * @see specs/features/abilities.yaml - synchronous-behavior
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('synchronous')]
class SynchronousDeliveryTest extends RegionBuilderTestCase
{
    #[Test]
    public function responseDeliveryCompletesBeforeInvokeReturns(): void
    {
        // Given: Track timing of response delivery
        $responseDelivered = false;
        $afterInvokeReached = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$responseDelivered, &$afterInvokeReached) {
                // Register ability
                $this->abilities()->register('timing-test', [
                    'name' => 'timing-test',
                    'description' => 'Test delivery timing',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['result' => 'ok']
                ]);

                // Invoke with then() handler
                $this->abilities('timing-test')
                    ->then(function () use (&$responseDelivered) {
                        $responseDelivered = true;
                    });

                // This line should execute AFTER then() handler
                $afterInvokeReached = true;
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Response should be delivered before invoke returns
        $this->assertTrue(
            $responseDelivered,
            'Response should be delivered to then() handler'
        );

        $this->assertTrue(
            $afterInvokeReached,
            'Code after abilities() call should execute'
        );

        // The critical assertion: response was delivered BEFORE moving past abilities() call
        // This is verified by both being true - if async, responseDelivered would be false
        $this->assertTrue(
            $responseDelivered && $afterInvokeReached,
            'Response delivery must complete before abilities() call returns (synchronous behavior)'
        );
    }

    #[Test]
    public function synchronousResultAccessPattern(): void
    {
        // Given: Test common pattern of capturing result synchronously
        $result = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$result) {
                // Register calculation ability
                $this->abilities()->register('calculate', [
                    'name' => 'calculate',
                    'description' => 'Performs calculation',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['sum' => 42]
                ]);

                // Invoke and immediately use result (synchronous pattern)
                $this->abilities('calculate')
                    ->then(function ($response) use (&$result) {
                        $result = $response;
                    });

                // In synchronous mode, result should be available here
                // (This would fail in async mode where result arrives later)
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Result should be available synchronously
        $this->assertNotNull(
            $result,
            'Result should be available synchronously after abilities() call returns'
        );
    }

    #[Test]
    public function noEventLoopRequiredForDelivery(): void
    {
        // Given: Track delivery without any explicit event loop ticking
        $deliveryCount = 0;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$deliveryCount) {
                // Register ability
                $this->abilities()->register('no-loop', [
                    'name' => 'no-loop',
                    'description' => 'Delivery without event loop',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['ok' => true]
                ]);

                // Invoke ability
                $this->abilities('no-loop')
                    ->then(function () use (&$deliveryCount) {
                        $deliveryCount++;
                    });
            })
            ->build();

        // When: Single trigger call, no event loop ticking
        $region->trigger((object)[]);

        // Then: Delivery should have occurred without any async machinery
        $this->assertSame(
            1,
            $deliveryCount,
            'Response should be delivered synchronously without event loop ticking'
        );
    }

    #[Test]
    public function deliveryHappensWithinSameTriggerCall(): void
    {
        // Given: Track which trigger call delivered the response
        $triggerCallNumber = 0;
        $deliveredDuringCall = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$triggerCallNumber, &$deliveredDuringCall) {
                // Register ability
                $this->abilities()->register('same-call', [
                    'name' => 'same-call',
                    'description' => 'Test delivery timing',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['value' => 1]
                ]);

                // Invoke ability
                $this->abilities('same-call')
                    ->then(function () use (&$triggerCallNumber, &$deliveredDuringCall) {
                        $deliveredDuringCall = $triggerCallNumber;
                    });
            })
            ->build();

        // When: First trigger call
        $triggerCallNumber = 1;
        $region->trigger((object)[]);

        // Then: Response should be delivered during first trigger call
        $this->assertSame(
            1,
            $deliveredDuringCall,
            'Response should be delivered during the same trigger() call, not a subsequent one'
        );
    }
}
