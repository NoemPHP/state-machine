<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Discovery;

use Noem\State\Feature\Abilities\AbilityRegistry;
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
class GetByStateReturnsFilteredArrayTest extends TestCase
{
    public function testGetInteractionsForStateAbilityReturnsArrayOfInteractionDefinitionsMatchingState(): void
    {
        $builder = new RegionBuilder();

        $subscriptionFeature = new SubscriptionFeature();
        $messageFeature = new MessageFeature();
        $extendedState = new ExtendedState();
        $interactionFeature = new InteractionFeature();
        $abilitiesFeature = new AbilitiesFeature();
        $registryFeature = new InteractionRegistryFeature();

        $subscriptionFeature($builder->chainMail);
        $messageFeature($builder->chainMail);
        $extendedState($builder->chainMail);
        $interactionFeature($builder->chainMail);
        $abilitiesFeature($builder->chainMail);
        $registryFeature($builder->chainMail);

        $builder->chainMail->boot();

        $interactionRegistry = $builder->chainMail->get(InteractionRegistry::class);
        $abilityRegistry = $builder->chainMail->get(AbilityRegistry::class);

        $definition1 = new InteractionDefinition(
            id: 'ready_confirm',
            type: 'confirm',
            state: 'ready',
            question: 'Ready?'
        );
        $definition2 = new InteractionDefinition(
            id: 'processing_select',
            type: 'select',
            state: 'processing',
            question: 'Choose'
        );
        $definition3 = new InteractionDefinition(
            id: 'ready_prompt',
            type: 'prompt',
            state: 'ready',
            question: 'Enter name'
        );

        $interactionRegistry->register($definition1);
        $interactionRegistry->register($definition2);
        $interactionRegistry->register($definition3);

        $ability = $abilityRegistry->get('get-interactions-for-state');
        $result = ($ability->handler)(['state' => 'ready']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('interactions', $result);
        $this->assertCount(2, $result['interactions']);

        $ids = array_column($result['interactions'], 'id');
        $this->assertContains('ready_confirm', $ids);
        $this->assertContains('ready_prompt', $ids);
        $this->assertNotContains('processing_select', $ids);
    }
}
