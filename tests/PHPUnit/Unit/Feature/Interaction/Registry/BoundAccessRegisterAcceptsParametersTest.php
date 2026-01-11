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
class BoundAccessRegisterAcceptsParametersTest extends TestCase
{
    public function testBoundAccessRegisterInteractionAcceptsStringIdAndInteractionDefinitionParameters(): void
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

        $paramsValid = false;

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $builder->onEnter('ready', function (object $trigger) use (&$paramsValid): void {
            $id = 'test_interaction';
            $definition = new InteractionDefinition(
                id: $id,
                type: 'prompt',
                state: 'ready',
                question: 'Enter name',
                metadata: ['placeholder' => 'John Doe']
            );

            // Verify parameters are accepted
            $this->registerInteraction($id, $definition);
            $paramsValid = true;
        });

        $region = $builder->build();
        $region->trigger((object)[]);

        $this->assertTrue($paramsValid);
    }
}
