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
 * Acceptance Criteria: RegionBuilder.presentation() returns RegionBuilder instance for chaining
 * Intent: Supports fluent builder pattern for multiple registration calls
 */
#[CoversClass(PresentationFeature::class)]
#[CoversClass(RegionBuilder::class)]
final class ReturnsBuilderTest extends RegionBuilderTestCase
{
    public function testReturnsBuilderForChaining(): void
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
        $registry->setSchemas([
            'key1' => ['type' => 'string'],
            'key2' => ['type' => 'string'],
            'key3' => ['type' => 'string']
        ]);

        // Test fluent chaining
        $result = $builder
            ->presentation('key1', 'Label 1', 'Intent 1')
            ->presentation('key2', 'Label 2', 'Intent 2')
            ->presentation('key3', 'Label 3', 'Intent 3');

        $this->assertSame($builder, $result, 'presentation() should return same builder instance');
        $this->assertCount(3, $registry->all(), 'All presentations should be registered');
    }
}
