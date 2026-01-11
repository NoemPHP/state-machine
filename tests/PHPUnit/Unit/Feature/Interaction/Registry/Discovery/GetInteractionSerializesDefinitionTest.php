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
class GetInteractionSerializesDefinitionTest extends TestCase
{
    public function testGetInteractionAbilitySerializesDefinitionToJsonCompatibleArray(): void
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

        $definition = new InteractionDefinition(
            id: 'test_select',
            type: 'select',
            state: 'processing',
            question: 'Choose option',
            options: ['a' => 'Option A', 'b' => 'Option B'],
            constraints: ['maxSelections' => 1],
            metadata: ['helpText' => 'Select one option']
        );

        $interactionRegistry->register($definition);

        $ability = $abilityRegistry->get('get-interaction');
        $result = ($ability->handler)(['id' => 'test_select']);

        $this->assertIsArray($result);
        $this->assertSame('test_select', $result['id']);
        $this->assertSame('select', $result['type']);
        $this->assertSame('processing', $result['state']);
        $this->assertSame('Choose option', $result['question']);
        $this->assertSame(['a' => 'Option A', 'b' => 'Option B'], $result['options']);
        $this->assertSame(['maxSelections' => 1], $result['constraints']);
        $this->assertSame(['helpText' => 'Select one option'], $result['metadata']);
    }
}
