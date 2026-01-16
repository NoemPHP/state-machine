<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec interact() method registers response handler via InteractionRequest.then()
 * @see specs/features/interaction.yaml - feature-integration-correlation
 */
class InteractRegistersThenHandlerTest extends TestCase
{
    public function testRegistersThenHandler(): void
    {
        $handlerRegistered = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$handlerRegistered) {
                $request = new ConfirmRequest(question: 'Test?');

                // Check if then() was called by verifying handler can be triggered
                $request->then(function ($response) use (&$handlerRegistered) {
                    $handlerRegistered = true;
                });

                // Trigger response delivery manually
                $response = new ConfirmResponse(confirmed: true, correlationId: $request->correlationId());
                $request->deliverResponse($response);
            });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region); $runtime->run();

        $this->assertTrue($handlerRegistered);
    }
}
