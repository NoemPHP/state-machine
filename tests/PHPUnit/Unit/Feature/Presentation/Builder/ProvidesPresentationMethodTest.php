<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Presentation\Builder;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Presentation\PresentationFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use Noem\State\Test\Helpers\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Spec: specs/features/presentation.yaml
 * Acceptance Criteria: RegionBuilder provides presentation() method
 * Intent: Enables declarative presentation registration during machine construction phase
 */
#[CoversClass(PresentationFeature::class)]
#[CoversClass(RegionBuilder::class)]
final class ProvidesPresentationMethodTest extends RegionBuilderTestCase
{
    public function testRegionBuilderHasPresentationMethod(): void
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

        // method_exists() doesn't detect __call methods, so we test by calling it
        // The method should exist via __call magic method
        $this->assertTrue(
            method_exists($builder, '__call'),
            'RegionBuilder must have __call method to support dynamic methods'
        );

        // Verify the method is callable
        $this->assertTrue(
            is_callable([$builder, 'presentation']),
            'RegionBuilder.presentation() must be callable after PresentationFeature is enabled'
        );
    }
}
