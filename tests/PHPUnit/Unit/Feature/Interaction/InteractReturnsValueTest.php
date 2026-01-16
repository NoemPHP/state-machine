<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec interact() method returns response value when response.cancelled is false
 * @see specs/features/interaction.yaml - feature-integration-correlation
 */
class InteractReturnsValueTest extends TestCase
{
    public function testReturnsResponseValue(): void
    {
        $returnedValue = null;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$returnedValue) {
                $request = new ConfirmRequest(question: 'Test?');

                $request->then(function ($response) {
                    // Response handler
                });

                $generator = $this->interact($request);
                $generator->current(); // Start generator

                // Deliver successful response
                $response = new ConfirmResponse(confirmed: true, cancelled: false, correlationId: $request->correlationId());
                $request->deliverResponse($response);

                // Get return value
                $generator->next();
                $returnedValue = $generator->getReturn();
            });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region); $runtime->run();

        $this->assertTrue($returnedValue, 'interact() should return response value');
    }
}
