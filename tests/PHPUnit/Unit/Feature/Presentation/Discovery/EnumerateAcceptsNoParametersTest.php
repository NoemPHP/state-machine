<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Presentation\Discovery;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use Noem\State\RuntimeConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PresentationFeature::class)]
class EnumerateAcceptsNoParametersTest extends TestCase
{
    public function testEnumeratePresentationsAcceptsNoParameters(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AbilitiesFeature(),
            new PresentationFeature()
        );

        $region = $builder->setStates('test')->build();
        $abilityRegistry = $builder->chainMail->get(\Noem\State\Feature\Abilities\AbilityRegistry::class);

        $this->assertInstanceOf(\Noem\State\Feature\Abilities\AbilityRegistry::class, $abilityRegistry);

        // Get the enumerate-presentations ability
        $definition = $abilityRegistry->get('enumerate-presentations');
        $this->assertNotNull($definition);

        // The ability should not require parameters (schema allows empty)
        $schema = $definition->parameterSchema;
        $this->assertIsArray($schema);
        // Schema should allow calling with no parameters
    }

}
