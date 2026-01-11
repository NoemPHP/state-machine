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
class BuilderRegistrationAvailableAfterBuildTest extends TestCase
{
    public function testInteractionsRegisteredViaRegionBuilderAvailableAfterBuildCompletes(): void
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

        // Register multiple interactions via BuildSteps
        $builder->addBuildStep(
            new RegisterInteraction(
                'deploy_confirm',
                new InteractionDefinition(
                    id: 'deploy_confirm',
                    type: 'confirm',
                    state: 'deploying',
                    question: 'Deploy to production?',
                    metadata: ['defaultValue' => false]
                )
            )
        );

        $builder->addBuildStep(
            new RegisterInteraction(
                'select_backend',
                new InteractionDefinition(
                    id: 'select_backend',
                    type: 'select',
                    state: 'configuring',
                    question: 'Choose database backend',
                    options: ['mysql' => 'MySQL', 'postgres' => 'PostgreSQL']
                )
            )
        );

        // Build region
        $builder->setStates('deploying', 'configuring');
        $builder->markInitial('deploying');
        $region = $builder->build();

        // Verify both interactions are available
        $registry = $builder->chainMail->get(InteractionRegistry::class);

        $deployConfirm = $registry->get('deploy_confirm');
        $this->assertNotNull($deployConfirm);
        $this->assertSame('confirm', $deployConfirm->type);

        $selectBackend = $registry->get('select_backend');
        $this->assertNotNull($selectBackend);
        $this->assertSame('select', $selectBackend->type);
    }
}
