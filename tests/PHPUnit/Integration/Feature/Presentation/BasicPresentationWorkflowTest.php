<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Presentation;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Presentation\PresentationRegistry;
use Noem\State\Feature\Presentation\RegionPresentation;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration test verifying basic presentation registration and retrieval workflow
 */
class BasicPresentationWorkflowTest extends RegionBuilderTestCase
{
    #[Test]
    public function registryIsAvailableAfterFeatureLoad(): void
    {
        $builder = $this->builder
            ->enableFeatures(
                new SubscriptionFeature(),
                new MessageFeature(),
                new ExtendedState(),
                new JsonSchemaFeature(),
                new AbilitiesFeature(),
                new PresentationFeature()
            );

        // Build the region to trigger feature resolution
        $builder->setStates('initial')->build();

        $registry = $builder->chainMail->get(PresentationRegistry::class);

        $this->assertInstanceOf(PresentationRegistry::class, $registry);
    }

    #[Test]
    public function canRegisterPresentationDirectly(): void
    {
        $builder = $this->builder
            ->enableFeatures(
                new SubscriptionFeature(),
                new MessageFeature(),
                new ExtendedState(),
                new JsonSchemaFeature(),
                new AbilitiesFeature(),
                new PresentationFeature()
            );

        // Build the region to trigger feature resolution
        $builder->setStates('initial')->build();

        $registry = $builder->chainMail->get(PresentationRegistry::class);

        // Set up schema so registration doesn't throw
        $registry->setSchemas(['testKey' => ['type' => 'string']]);

        $presentation = new RegionPresentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test intent'
        );

        $registry->register($presentation);

        $retrieved = $registry->get('testKey');
        $this->assertSame($presentation, $retrieved);
    }
}
