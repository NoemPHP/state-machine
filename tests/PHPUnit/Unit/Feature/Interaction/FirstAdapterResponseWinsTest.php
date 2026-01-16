<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec First framework adapter response wins via MessageFeature auto-unsubscribe
 * @see specs/features/interaction.yaml - framework-adapter-subscription
 */
class FirstAdapterResponseWinsTest extends TestCase
{
    public function testFirstResponseWins(): void
    {
        $adapter1Called = false;
        $adapter2Called = false;
        $receivedValue = null;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Async\AsyncFeature(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger) use (&$receivedValue): \Generator {
                // interact() returns the value directly (boolean for ConfirmRequest)
                $receivedValue = yield from $this->interact(new ConfirmRequest(question: 'Test?'));
            });
        $builder->initialState('test');

        $region = $builder->build();

        // First adapter - responds with true
        $region->on(function (\Noem\State\Feature\Interaction\InteractionRequest $request) use (&$adapter1Called) {
            $adapter1Called = true;
            $request->deliverResponse(new ConfirmResponse(confirmed: true, cancelled: false, correlationId: $request->correlationId()));
        });

        // Second adapter - tries to respond with false (should be ignored by Message first-response-wins)
        $region->on(function (\Noem\State\Feature\Interaction\InteractionRequest $request) use (&$adapter2Called) {
            $adapter2Called = true;
            $request->deliverResponse(new ConfirmResponse(confirmed: false, cancelled: false, correlationId: $request->correlationId()));
        });

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($adapter1Called && $adapter2Called, 'Both adapters should be called');
        $this->assertTrue($receivedValue, 'First response (true) should win, not second (false)');
    }
}
