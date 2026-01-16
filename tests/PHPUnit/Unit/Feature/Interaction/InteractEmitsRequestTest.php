<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec interact() method emits InteractionRequest via NotificationChain
 * @see specs/features/interaction.yaml - feature-integration-correlation
 */
class InteractEmitsRequestTest extends TestCase
{
    public function testEmitsRequestViaNotificationChain(): void
    {
        $requestReceived = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$requestReceived) {
                $request = new ConfirmRequest(question: 'Test?');

                // Simulate response to prevent blocking
                $request->then(function ($response) {
                    // Response handled
                });

                $generator = $this->interact($request);

                // Emit response immediately
                $response = new ConfirmResponse(confirmed: true, correlationId: $request->correlationId());
                $request->deliverResponse($response);

                // Consume generator
            foreach ($generator as $value) {
                break;
            }
        });
        $builder->initialState('test');

        $region = $builder->build();

        // Subscribe to InteractionRequest to verify emission
        $region->on(function (InteractionRequest $request) use (&$requestReceived) {
            $requestReceived = true;
        });

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($requestReceived, 'InteractionRequest should be emitted via NotificationChain');
    }
}
