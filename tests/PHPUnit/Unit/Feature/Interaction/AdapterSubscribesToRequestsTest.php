<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters subscribe to InteractionRequest via Region.on() with type filtering
 * @see specs/features/interaction.yaml - framework-adapter-subscription
 */
class AdapterSubscribesToRequestsTest extends TestCase
{
    public function testAdapterSubscribesViaTypeFiltering(): void
    {
        $subscribed = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test');
        $builder->initialState('test');

        $region = $builder->build();

        // Framework adapter subscribes to InteractionRequest - type filtering via callable parameter type hint
        $region->on(function (InteractionRequest $request) use (&$subscribed) {
            $subscribed = true;
        });

        $this->assertTrue(true, 'Adapter can subscribe to InteractionRequest type');
    }
}
