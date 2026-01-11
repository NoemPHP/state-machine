<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Discovery;

use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class GetByStateRequiresStateParameterTest extends TestCase
{
    public function testGetInteractionsForStateAbilityRequiresStateParameterInRequest(): void
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

        $abilityRegistry = $builder->chainMail->get(AbilityRegistry::class);
        $ability = $abilityRegistry->get('get-interactions-for-state');

        $this->assertNotNull($ability);
        $this->assertContains('state', $ability->parameterSchema['required'] ?? []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('get-interactions-for-state requires "state" parameter');
        ($ability->handler)([]);
    }
}
