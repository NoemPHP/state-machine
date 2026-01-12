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
use Noem\State\Region;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Spec: specs/features/presentation.yaml
 * Acceptance Criteria: RegionBuilder.presentation() accepts optional predicate parameter
 * Intent: Enables conditional presentation exposure based on context evaluation
 */
#[CoversClass(PresentationFeature::class)]
#[CoversClass(RegionBuilder::class)]
final class AcceptsPredicateParameterTest extends RegionBuilderTestCase
{
    public function testAcceptsPredicateParameter(): void
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

        $predicate = fn(Region $region) => true;

        // Call with predicate
        $builder->presentation(
            key: 'testKey',
            label: 'Label',
            intent: 'Intent',
            metadata: null,
            predicate: $predicate
        );

        $presentation = $registry->get('testKey');
        $this->assertSame($predicate, $presentation->predicate);
    }
}
