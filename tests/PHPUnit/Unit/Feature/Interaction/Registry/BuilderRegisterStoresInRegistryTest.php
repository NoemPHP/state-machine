<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\BuildStep\RegisterInteraction;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RegisterInteraction::class)]
class BuilderRegisterStoresInRegistryTest extends TestCase
{
    public function testRegisterInteractionStoresDefinitionInInteractionRegistry(): void
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

        $definition = new InteractionDefinition(
            id: 'deploy_confirm',
            type: 'confirm',
            state: 'deploying',
            question: 'Deploy to production?',
            metadata: ['defaultValue' => false]
        );

        $builder->addBuildStep(
            new RegisterInteraction('deploy_confirm', $definition)
        );

        // Build region to execute BuildSteps
        $builder->setStates('deploying');
        $builder->markInitial('deploying');
        $region = $builder->build();

        // Retrieve registry from ChainMail
        $registry = $builder->chainMail->get(InteractionRegistry::class);

        $retrieved = $registry->get('deploy_confirm');
        $this->assertNotNull($retrieved);
        $this->assertSame('confirm', $retrieved->type);
        $this->assertSame('Deploy to production?', $retrieved->question);
    }
}
