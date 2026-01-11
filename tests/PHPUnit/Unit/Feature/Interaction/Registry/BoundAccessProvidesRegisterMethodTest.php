<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class BoundAccessProvidesRegisterMethodTest extends TestCase
{
    public function testBoundAccessProvidesRegisterInteractionMethodInStateCallbacks(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new InteractionFeature(),
            new AbilitiesFeature(),
            new InteractionRegistryFeature()
        );

        $methodCalled = false;

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $builder->onEnter('ready', function (object $trigger) use (&$methodCalled): void {
            // Verify method exists and is callable
            $definition = new InteractionDefinition(
                id: 'proceed_confirm',
                type: 'confirm',
                state: 'ready',
                question: 'Proceed?',
                metadata: ['defaultValue' => true]
            );

            $this->registerInteraction('proceed_confirm', $definition);
            $methodCalled = true;
        });

        $region = $builder->build();
        $region->trigger((object)[]);

        $this->assertTrue($methodCalled);
    }
}
