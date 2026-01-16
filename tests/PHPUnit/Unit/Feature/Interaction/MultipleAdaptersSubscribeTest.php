<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec Multiple framework adapters can subscribe to same InteractionRequest type
 * @see specs/features/interaction.yaml - framework-adapter-subscription
 */
class MultipleAdaptersSubscribeTest extends TestCase
{
    public function testMultipleAdaptersCanSubscribe(): void
    {
        $adapter1Called = false;
        $adapter2Called = false;

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

        // Multiple adapters subscribe - type filtering via callable parameter type hint
        $region->on(function (InteractionRequest $request) use (&$adapter1Called) {
            $adapter1Called = true;
            $request->deliverResponse(new ConfirmResponse(true, false, $request->correlationId()));
        });

        $region->on(function (InteractionRequest $request) use (&$adapter2Called) {
            $adapter2Called = true;
        });

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($adapter1Called && $adapter2Called, 'Multiple adapters should receive requests');
    }
}
