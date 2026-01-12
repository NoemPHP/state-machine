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
 * Acceptance Criteria: Presentations registered via RegionBuilder available after build() completes
 * Intent: Ensures registration occurs early in build phase before state callbacks execute
 */
#[CoversClass(PresentationFeature::class)]
#[CoversClass(RegionBuilder::class)]
final class AvailableAfterBuildTest extends RegionBuilderTestCase
{
    public function testPresentationsAvailableAfterBuild(): void
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
        $registry->setSchemas(['testKey' => ['type' => 'string']]);

        // Register presentation
        $builder->presentation('testKey', 'Label', 'Intent');

        // Build region
        $region = $builder->setStates('processing')->build();

        // Verify presentation is still available after build
        $presentation = $registry->get('testKey');
        $this->assertNotNull($presentation, 'Presentation must be available after build()');
        $this->assertSame('testKey', $presentation->key);
    }
}
