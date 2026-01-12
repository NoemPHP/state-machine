<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Presentation\Builder;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Spec: specs/features/presentation.yaml
 * Acceptance Criteria: RegionBuilder.presentation() creates RegionPresentation and registers in PresentationRegistry
 * Intent: Delegates storage to registry ensuring presentations available before machine starts
 */
#[CoversClass(PresentationFeature::class)]
#[CoversClass(RegionBuilder::class)]
final class RegistersInRegistryTest extends RegionBuilderTestCase
{
    public function testRegistersInPresentationRegistry(): void
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

        // Setup schema
        $builder->setStates('idle')->build();
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $registry->setSchemas(['key1' => ['type' => 'string'], 'key2' => ['type' => 'number']]);

        // Register multiple presentations
        $builder->presentation('key1', 'Label 1', 'Intent 1');
        $builder->presentation('key2', 'Label 2', 'Intent 2');

        // Verify both are in registry
        $this->assertNotNull($registry->get('key1'));
        $this->assertNotNull($registry->get('key2'));
        $this->assertCount(2, $registry->all());
    }
}
