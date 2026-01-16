<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec interact() method yields until matching response received
 * @see specs/features/interaction.yaml - feature-integration-correlation
 */
class InteractYieldsUntilResponseTest extends TestCase
{
    public function testYieldsUntilResponseReceived(): void
    {
        $yielded = false;
        $afterResponse = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$yielded, &$afterResponse) {
                $request = new ConfirmRequest(question: 'Test?');

                $request->then(function ($response) {
                    // Response received
                });

                $generator = $this->interact($request);

                // First yield should occur while waiting
                $generator->current();
                $yielded = true;

                // Deliver response
                $response = new ConfirmResponse(confirmed: true, correlationId: $request->correlationId());
                $request->deliverResponse($response);

                // After response, generator should complete
                $generator->next();
                $afterResponse = !$generator->valid();
        });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($yielded, 'interact() should yield while waiting');
        $this->assertTrue($afterResponse, 'interact() should complete after response');
    }
}
