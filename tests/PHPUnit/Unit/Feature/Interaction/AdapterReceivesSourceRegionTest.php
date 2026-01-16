<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters receive source Region reference in subscription callback
 * @see specs/features/interaction.yaml - framework-adapter-subscription
 */
class AdapterReceivesSourceRegionTest extends TestCase
{
    public function testAdapterReceivesSourceRegion(): void
    {
        $receivedRegion = null;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Async\AsyncFeature(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger): \Generator {
                yield from $this->interact(new ConfirmRequest(question: 'Test?'));
        });
        $builder->initialState('test');

        $region = $builder->build();

        // Subscribe and capture source region
        $region->on(function (InteractionRequest $request, $source = null) use (&$receivedRegion) {
            $receivedRegion = $source;
            $request->deliverResponse(new ConfirmResponse(true, false, $request->correlationId()));
        });

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertInstanceOf(Region::class, $receivedRegion, 'Adapter should receive source Region reference');
    }
}
