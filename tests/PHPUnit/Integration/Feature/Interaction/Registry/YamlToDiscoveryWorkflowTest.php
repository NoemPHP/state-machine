<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Interaction\Registry;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Feature\Interaction\InteractionRegistry;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
#[CoversClass(InteractionRegistry::class)]
#[CoversClass(InteractionDefinition::class)]
class YamlToDiscoveryWorkflowTest extends TestCase
{
    public function testCompleteWorkflowFromYamlDeclarationToAbilityBasedDiscovery(): void
    {
        // Create YAML configuration with interactions
        $yamlContent = <<<YAML
initial: ready
states:
  - name: ready
    interactions:
      - id: confirm_deploy
        type: confirm
        question: "Deploy to production?"
        metadata:
          helpText: "This will deploy to production servers"
YAML;

        $tempFile = tempnam(sys_get_temp_dir(), 'interaction_test_') . '.yaml';
        file_put_contents($tempFile, $yamlContent);

        try {
            // Build machine with all required features
            $builder = new RegionBuilder();
            $builder->enableFeatures(
                new SubscriptionFeature(),
                new MessageFeature(),
                new ExtendedState(),
                new InteractionFeature(),
                new AbilitiesFeature(),
                new InteractionRegistryFeature(),
                new RegionLoader()
            );

            // Get registry reference before building
            $interactionRegistry = null;
            $builder->chainMail->use(function (?InteractionRegistry $r = null) use (&$interactionRegistry) {
                $interactionRegistry = $r;
            });

            $region = $builder->build([
                'loader' => [
                    'yaml' => file_get_contents($tempFile),
                ],
            ]);

            $this->assertNotNull($interactionRegistry);

            // Verify interaction is retrievable
            $definition = $interactionRegistry->get('confirm_deploy');
            $this->assertInstanceOf(InteractionDefinition::class, $definition);
            $this->assertSame('confirm_deploy', $definition->id);
            $this->assertSame('confirm', $definition->type);
            $this->assertSame('ready', $definition->state);
            $this->assertSame('Deploy to production?', $definition->question);

            // Verify metadata
            $this->assertIsArray($definition->metadata);
            $this->assertSame('This will deploy to production servers', $definition->metadata['helpText']);

            // Verify interaction discoverable via getByState
            $stateInteractions = $interactionRegistry->getByState('ready');
            $this->assertCount(1, $stateInteractions);
            $this->assertSame($definition, $stateInteractions[0]);

            // Verify interaction discoverable via all()
            $allInteractions = $interactionRegistry->all();
            $this->assertCount(1, $allInteractions);
            $this->assertContains($definition, $allInteractions);
        } finally {
            unlink($tempFile);
        }
    }
}
