<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters receive InteractionRequest events via SubscriptionFeature type filtering
 * @see specs/features/interaction.yaml - feature-integration-emission
 */
class AdapterReceivesRequestTest extends TestCase
{
    public function testFrameworkAdapterReceivesRequest(): void
    {
        $receivedRequest = null;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) {
                $request = new ConfirmRequest(question: 'Test?');
                $request->then(function ($r) {});

                $generator = $this->interact($request);
                $generator->current();

                // Provide response
                $request->deliverResponse(new ConfirmResponse(confirmed: true, correlationId: $request->correlationId()));
                $generator->next();
            });
        $builder->initialState('test');

        $region = $builder->build();

        // Subscribe via type filtering (framework adapter pattern)
        $region->on(function (InteractionRequest $request) use (&$receivedRequest) {
            $receivedRequest = $request;
        });

        $runtime = new \Noem\State\StandardRuntime($region); $runtime->run();

        $this->assertInstanceOf(InteractionRequest::class, $receivedRequest);
    }
}
