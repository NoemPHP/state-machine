<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters emit InteractionResponse via Region.notificationChain
 * @see specs/features/interaction.yaml - feature-integration-emission
 */
class AdapterEmitsResponseTest extends TestCase
{
    public function testAdapterEmitsResponseViaNotificationChain(): void
    {
        $responseEmitted = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$responseEmitted) {
                $request = new ConfirmRequest(question: 'Test?');

                $request->then(function ($r) use (&$responseEmitted) {
                    $responseEmitted = true;
                });

                $generator = $this->interact($request);
                $generator->current();

                // Simulate framework adapter emitting response
                $response = new ConfirmResponse(confirmed: true, correlationId: $request->correlationId());
                $request->deliverResponse($response);

                $generator->next();
        });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($responseEmitted, 'Framework adapter should emit response via notificationChain');
    }
}
