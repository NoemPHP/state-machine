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
class EnumerateSerializesDefinitionsTest extends TestCase
{
    public function testEnumerateInteractionsAbilitySerializesDefinitionsToJsonCompatibleArrays(): void
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
            id: 'test_confirm',
            type: 'confirm',
            state: 'ready',
            question: 'Proceed?',
            metadata: ['defaultValue' => true]
        );

        $interactionRegistry->register($definition);

        $ability = $abilityRegistry->get('enumerate-interactions');
        $result = ($ability->handler)(null);

        $this->assertIsArray($result['interactions'][0]);
        $this->assertSame('test_confirm', $result['interactions'][0]['id']);
        $this->assertSame('confirm', $result['interactions'][0]['type']);
        $this->assertSame('ready', $result['interactions'][0]['state']);
        $this->assertSame('Proceed?', $result['interactions'][0]['question']);
        $this->assertSame(['defaultValue' => true], $result['interactions'][0]['metadata']);
    }
}
