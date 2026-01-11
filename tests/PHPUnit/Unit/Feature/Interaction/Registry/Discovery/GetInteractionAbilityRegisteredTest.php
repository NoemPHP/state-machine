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
class GetInteractionAbilityRegisteredTest extends TestCase
{
    public function testAbilityRegistryContainsGetInteractionAbilityAfterFeatureLoads(): void
    {
        // Use RegionBuilder to properly initialize all core chains
        $builder = new RegionBuilder();

        // Manually invoke features (normally done by build() via FeatureRegistry)
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

        // Boot ChainMail to initialize services
        $builder->chainMail->boot();

        // Retrieve AbilityRegistry from ChainMail
        $registry = $builder->chainMail->get(AbilityRegistry::class);

        $ability = $registry->get('get-interaction');
        $this->assertNotNull($ability);
        $this->assertSame('get-interaction', $ability->name);
    }
}
