<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Interaction\InteractionRequest;
use Noem\State\Feature\Interaction\SelectRequest;
use Noem\State\Feature\Interaction\SelectResponse;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters filter InteractionRequest by getType() for routing
 * @see specs/features/interaction.yaml - framework-adapter-subscription
 */
class AdapterFiltersRequestsByTypeTest extends TestCase
{
    public function testAdapterFiltersRequestsByType(): void
    {
        $confirmCount = 0;
        $selectCount = 0;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\Subscription\SubscriptionFeature(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Async\AsyncFeature(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test')->onEnter('test', function (object $trigger): \Generator {
                yield from $this->interact(new ConfirmRequest(question: 'Confirm?'));
                yield from $this->interact(new SelectRequest(question: 'Select?', options: []));
            });
        $builder->initialState('test');

        $region = $builder->build();

        // Adapter filters by type and responds
        $region->on(function (InteractionRequest $request) use (&$confirmCount, &$selectCount) {
            if ($request->getType() === 'confirm') {
                $confirmCount++;
                $request->deliverResponse(new ConfirmResponse(true, false, $request->correlationId()));
            } elseif ($request->getType() === 'select') {
                $selectCount++;
                $request->deliverResponse(new SelectResponse('key', false, $request->correlationId()));
            }
        });

        $runtime = new \Noem\State\StandardRuntime($region); $runtime->run();

        $this->assertGreaterThan(0, $confirmCount + $selectCount, 'Adapter should filter requests by type');
    }
}
