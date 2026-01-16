<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec Nested interact() calls complete in LIFO order
 * @see specs/features/interaction.yaml - feature-integration-correlation
 */
class NestedInteractionsTest extends TestCase
{
    public function testNestedInteractionsCompleteLIFO(): void
    {
        $completionOrder = [];

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$completionOrder) {
                $outer = new ConfirmRequest(question: 'Outer?');
                $inner = new ConfirmRequest(question: 'Inner?');

                $outer->then(function ($r) use (&$completionOrder) {
                    $completionOrder[] = 'outer';
                });

                $inner->then(function ($r) use (&$completionOrder) {
                    $completionOrder[] = 'inner';
                });

                // Deliver responses in LIFO order (inner first, then outer)
                $inner->deliverResponse(new ConfirmResponse(confirmed: true, correlationId: $inner->correlationId()));
                $outer->deliverResponse(new ConfirmResponse(confirmed: true, correlationId: $outer->correlationId()));
        });
        $builder->initialState('test');

        $region = $builder->build();
        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertSame(['inner', 'outer'], $completionOrder, 'Nested interactions should complete LIFO');
    }
}
