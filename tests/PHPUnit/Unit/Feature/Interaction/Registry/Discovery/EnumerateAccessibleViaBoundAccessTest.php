<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Interaction\Registry\Discovery;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Interaction\InteractionDefinition;
use Noem\State\Feature\Interaction\InteractionFeature;
use Noem\State\Feature\Interaction\InteractionRegistryFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InteractionRegistryFeature::class)]
class EnumerateAccessibleViaBoundAccessTest extends TestCase
{
    public function testEnumerateInteractionsAbilityAccessibleViaAbilitiesInStateCallbacks(): void
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

        $result = null;

        $builder->setStates('ready');
        $builder->markInitial('ready');
        $builder->onEnter('ready', function (object $trigger) use (&$result): void {
            $definition = new InteractionDefinition(
                id: 'test_confirm',
                type: 'confirm',
                state: 'ready',
                question: 'Proceed?'
            );
            $this->registerInteraction('test_confirm', $definition);

            // Use ->then() to receive the response with the handler result
            $this->abilities('enumerate-interactions')
                ->then(function ($response) use (&$result) {
                    $result = $response->parameters;
                });
        });

        $region = $builder->build();
        $region->trigger((object)[]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('interactions', $result);
        $this->assertCount(1, $result['interactions']);
        $this->assertSame('test_confirm', $result['interactions'][0]['id']);
    }
}
