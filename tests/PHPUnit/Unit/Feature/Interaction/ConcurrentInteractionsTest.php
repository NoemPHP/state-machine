<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec Multiple concurrent interact() calls maintain separate correlation contexts
 * @see specs/features/interaction.yaml - feature-integration-correlation
 */
class ConcurrentInteractionsTest extends TestCase
{
    public function testMaintainsSeparateCorrelationContexts(): void
    {
        $response1 = null;
        $response2 = null;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$response1, &$response2) {
                $request1 = new ConfirmRequest(question: 'Question 1?');
                $request2 = new ConfirmRequest(question: 'Question 2?');

                $request1->then(function ($r) use (&$response1) {
                    $response1 = $r->confirmed;
                });

                $request2->then(function ($r) use (&$response2) {
                    $response2 = $r->confirmed;
                });

                // Deliver responses with correct correlation IDs
                $request1->deliverResponse(new ConfirmResponse(confirmed: true, correlationId: $request1->correlationId()));
                $request2->deliverResponse(new ConfirmResponse(confirmed: false, correlationId: $request2->correlationId()));
            });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region); $runtime->run();

        $this->assertTrue($response1, 'First interaction should receive true');
        $this->assertFalse($response2, 'Second interaction should receive false');
    }
}
