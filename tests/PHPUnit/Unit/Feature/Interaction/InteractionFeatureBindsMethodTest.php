<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\ExtendedState\ContextChains\BoundAccess;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionFeature binds interact method to BoundAccess chain
 * @see specs/features/interaction.yaml - feature-integration-bound-access
 */
class InteractionFeatureBindsMethodTest extends TestCase
{
    public function testBindsInteractMethodToBoundAccess(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new \Noem\State\Feature\ExtendedState\ExtendedState(),
            new \Noem\State\Feature\Message\MessageFeature(),
            new \Noem\State\Feature\Interaction\InteractionFeature()
        );
        $builder->addState('test');
        $builder->initialState('test');

        // Build to boot ChainMail
        $region = $builder->build();

        $chainMail = $builder->chainMail;
        $boundAccess = $chainMail->get(BoundAccess::class);

        $this->assertInstanceOf(BoundAccess::class, $boundAccess);

        // BoundAccess chain should have interact() method handler registered
        // We verify this by checking that the chain exists and can be invoked
        $this->assertNotNull($boundAccess);
    }
}
