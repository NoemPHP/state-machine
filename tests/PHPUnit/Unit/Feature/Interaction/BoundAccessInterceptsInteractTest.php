<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec BoundAccess intercepts $this->interact() method calls
 * @see specs/features/interaction.yaml - feature-integration-bound-access
 */
class BoundAccessInterceptsInteractTest extends TestCase
{
    public function testInterceptsInteractMethodCall(): void
    {
        $intercepted = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$intercepted) {
                $request = new ConfirmRequest(question: 'Test?');

                // Register response handler to capture response
                $request->then(function ($response) {
                    // Response received
                });

                // This should be intercepted by BoundAccess
                $generator = $this->interact($request);

                $intercepted = $generator instanceof \Generator;

                // Don't actually yield (would block test)
        });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($intercepted, 'interact() call was not intercepted by BoundAccess');
    }
}
