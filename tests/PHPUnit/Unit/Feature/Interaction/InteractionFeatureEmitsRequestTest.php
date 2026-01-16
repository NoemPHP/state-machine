<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionFeature emits InteractionRequest via Region.notificationChain
 * @see specs/features/interaction.yaml - feature-integration-emission
 */
class InteractionFeatureEmitsRequestTest extends TestCase
{
    public function testEmitsRequestViaNotificationChain(): void
    {
        $emitted = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Async\AsyncFeature(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger): \Generator {
                yield from $this->interact(new ConfirmRequest(question: 'Test?'));
            });
        $builder->initialState('test');

        $region = $builder->build();

        // Subscribe to verify emission
        $region->on(function (InteractionRequest $request) use (&$emitted) {
            $emitted = true;
            // Deliver response so interact() can complete
            $request->deliverResponse(new \Noem\State\Feature\Interaction\ConfirmResponse(
                confirmed: true,
                cancelled: false,
                correlationId: $request->correlationId()
            ));
        });

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($emitted, 'InteractionRequest should be emitted via notificationChain');
    }
}
