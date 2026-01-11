<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class RuntimeRegistrationImmediatelyAvailableTest extends TestCase
{
    public function testRuntimeRegistrationImmediatelyAvailableForDiscoveryInSameCallback(): void
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

        $foundImmediately = false;

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $builder->onEnter('ready', function (object $trigger) use ($builder, &$foundImmediately): void {
            // Register interaction via BoundAccess
            $this->registerInteraction(
                'dynamic_confirm',
                new InteractionDefinition(
                    id: 'dynamic_confirm',
                    type: 'confirm',
                    state: 'ready',
                    question: 'Proceed?',
                    metadata: []
                )
            );

            // Retrieve registry and verify immediately available
            $registry = $builder->chainMail->get(InteractionRegistry::class);
            $found = $registry->get('dynamic_confirm');
            $foundImmediately = ($found !== null);
        });

        $region = $builder->build();
        $region->trigger((object)[]);

        $this->assertTrue($foundImmediately);
    }
}
