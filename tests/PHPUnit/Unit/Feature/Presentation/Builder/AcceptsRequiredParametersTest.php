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
 * Acceptance Criteria: RegionBuilder.presentation() accepts key, label, intent parameters
 * Intent: Provides minimal required parameters for presentation registration
 */
#[CoversClass(PresentationFeature::class)]
#[CoversClass(RegionBuilder::class)]
final class AcceptsRequiredParametersTest extends RegionBuilderTestCase
{
    public function testAcceptsKeyLabelIntentParameters(): void
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

        // Setup schema for validation
        $builder->setStates('idle')->build();
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $registry->setSchemas(['testKey' => ['type' => 'string']]);

        // Call presentation() with required parameters
        $result = $builder->presentation(
            key: 'testKey',
            label: 'Test Label',
            intent: 'Test Intent'
        );

        // Verify it registers the presentation
        $presentation = $registry->get('testKey');
        $this->assertNotNull($presentation, 'Presentation should be registered');
        $this->assertSame('testKey', $presentation->key);
        $this->assertSame('Test Label', $presentation->label);
        $this->assertSame('Test Intent', $presentation->intent);
    }
}
