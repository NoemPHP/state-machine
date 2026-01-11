<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Interaction\Registry;

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
#[CoversClass(InteractionRegistry::class)]
#[CoversClass(InteractionDefinition::class)]
class ContractValidationWorkflowTest extends TestCase
{
    public function testCompleteWorkflowFromContractDeclarationToRuntimeValidation(): void
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

        $builder->setStates('setup', 'running');
        $builder->markInitial('setup');

        $builder->onEnter('setup', function (object $trigger): void {
            // Register interaction with specific contract
            $this->registerInteraction('confirm_proceed', new InteractionDefinition(
                id: 'confirm_proceed',
                type: 'confirm',
                state: 'setup',
                question: 'Proceed with setup?',
                metadata: ['defaultValue' => false]
            ));

            // Verify interaction is registered and retrievable
            $this->abilities('get-interaction', ['id' => 'confirm_proceed'])
                ->then(function ($response) {
                    $retrieved = $response->parameters;

                    if ($retrieved === null) {
                        throw new \RuntimeException('Failed to retrieve registered interaction');
                    }

                    // Verify contract properties
                    if ($retrieved['type'] !== 'confirm') {
                        throw new \RuntimeException('Type mismatch in contract');
                    }

                    if ($retrieved['state'] !== 'setup') {
                        throw new \RuntimeException('State mismatch in contract');
                    }
                });
        });

        $region = $builder->build();
        $region->trigger((object)[]);

        // Verify interaction is in registry after execution
        $registry = $builder->chainMail->get(InteractionRegistry::class);
        $definition = $registry->get('confirm_proceed');

        $this->assertNotNull($definition);
        $this->assertSame('confirm_proceed', $definition->id);
        $this->assertSame('confirm', $definition->type);
        $this->assertSame('setup', $definition->state);
        $this->assertSame('Proceed with setup?', $definition->question);
        $this->assertSame(['defaultValue' => false], $definition->metadata);

        // Verify contract is serializable
        $serialized = $definition->jsonSerialize();
        $this->assertIsArray($serialized);
        $this->assertArrayHasKey('id', $serialized);
        $this->assertArrayHasKey('type', $serialized);
        $this->assertArrayHasKey('state', $serialized);
        $this->assertArrayHasKey('question', $serialized);
        $this->assertArrayHasKey('metadata', $serialized);
    }
}
