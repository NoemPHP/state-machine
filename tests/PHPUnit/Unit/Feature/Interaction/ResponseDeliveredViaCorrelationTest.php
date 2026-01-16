<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec MessageFeature delivers InteractionResponse to waiting machine via correlation ID
 * @see specs/features/interaction.yaml - feature-integration-emission
 */
class ResponseDeliveredViaCorrelationTest extends TestCase
{
    public function testResponseDeliveredViaCorrelation(): void
    {
        $delivered = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$delivered) {
                $request = new ConfirmRequest(question: 'Test?');

                $request->then(function ($response) use (&$delivered, $request) {
                    // Verify response matches correlation
                    $delivered = $response->correlationId() === $request->correlationId();
                });

                // Deliver correlated response
                $response = new ConfirmResponse(confirmed: true, correlationId: $request->correlationId());
                $request->deliverResponse($response);
            });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region); $runtime->run();

        $this->assertTrue($delivered, 'Response should be delivered via correlation ID matching');
    }
}
