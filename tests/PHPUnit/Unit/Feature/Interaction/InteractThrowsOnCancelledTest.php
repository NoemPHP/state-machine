<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\InteractionCancelledException;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec interact() method throws InteractionCancelledException when response.cancelled is true
 * @see specs/features/interaction.yaml - feature-integration-correlation
 */
class InteractThrowsOnCancelledTest extends TestCase
{
    public function testThrowsOnCancelled(): void
    {
        $exceptionCaught = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$exceptionCaught) {
                $request = new ConfirmRequest(question: 'Test?');

                $request->then(function ($response) {
                    // Response handler
                });

            try {
                $generator = $this->interact($request);
                $generator->current(); // Start generator

                // Deliver cancelled response
                $response = new ConfirmResponse(confirmed: false, cancelled: true, correlationId: $request->correlationId());
                $request->deliverResponse($response);

                // This should throw
                $generator->next();
                $generator->getReturn();
            } catch (InteractionCancelledException $e) {
                $exceptionCaught = true;
            }
        });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($exceptionCaught, 'interact() should throw InteractionCancelledException when cancelled');
    }
}
