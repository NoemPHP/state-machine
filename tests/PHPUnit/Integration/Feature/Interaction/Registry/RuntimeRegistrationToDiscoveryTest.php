<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Interaction\Registry;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Abilities\AbilityRegistry;
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
class RuntimeRegistrationToDiscoveryTest extends TestCase
{
    public function testCompleteWorkflowFromRuntimeRegistrationToStateFilteredDiscovery(): void
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

        $discoveryResult = null;
        $stateFilteredResult = null;

        $builder->setStates('ready', 'processing', 'complete');
        $builder->markInitial('ready');

        $builder->onEnter('ready', function (object $trigger) use (&$discoveryResult, &$stateFilteredResult): void {
            // Register interactions dynamically at runtime
            $this->registerInteraction('ready_confirm', new InteractionDefinition(
                id: 'ready_confirm',
                type: 'confirm',
                state: 'ready',
                question: 'Are you ready?',
                metadata: ['defaultValue' => true]
            ));

            $this->registerInteraction('processing_select', new InteractionDefinition(
                id: 'processing_select',
                type: 'select',
                state: 'processing',
                question: 'Select action',
                options: ['continue' => 'Continue', 'pause' => 'Pause']
            ));

            // Discover all interactions
            $this->abilities('enumerate-interactions')
                ->then(function ($response) use (&$discoveryResult) {
                    $discoveryResult = $response->parameters;
                });

            // Discover state-filtered interactions
            $this->abilities('get-interactions-for-state', ['state' => 'ready'])
                ->then(function ($response) use (&$stateFilteredResult) {
                    $stateFilteredResult = $response->parameters;
                });
        });

        $region = $builder->build();
        $region->trigger((object)[]);

        // Verify complete enumeration
        $this->assertIsArray($discoveryResult);
        $this->assertArrayHasKey('interactions', $discoveryResult);
        $this->assertCount(2, $discoveryResult['interactions']);

        $ids = array_column($discoveryResult['interactions'], 'id');
        $this->assertContains('ready_confirm', $ids);
        $this->assertContains('processing_select', $ids);

        // Verify state-filtered discovery
        $this->assertIsArray($stateFilteredResult);
        $this->assertArrayHasKey('interactions', $stateFilteredResult);
        $this->assertCount(1, $stateFilteredResult['interactions']);
        $this->assertSame('ready_confirm', $stateFilteredResult['interactions'][0]['id']);
        $this->assertSame('ready', $stateFilteredResult['interactions'][0]['state']);

        // Verify interactions are also available directly from registry
        $registry = $builder->chainMail->get(InteractionRegistry::class);
        $readyInteraction = $registry->get('ready_confirm');
        $this->assertNotNull($readyInteraction);
        $this->assertSame('confirm', $readyInteraction->type);

        $processingInteraction = $registry->get('processing_select');
        $this->assertNotNull($processingInteraction);
        $this->assertSame('select', $processingInteraction->type);
    }
}
