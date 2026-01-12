<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Presentation\BoundAccess;

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
 * Acceptance Criteria: BoundAccess provides presentation() method in state callbacks
 * Intent: Enables dynamic presentation declaration during machine execution for context-dependent exposure
 */
#[CoversClass(PresentationFeature::class)]
final class ProvidesPresentationMethodTest extends RegionBuilderTestCase
{
    public function testPresentationMethodAvailableInStateCallbacks(): void
    {
        $isCallable = false;

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new SubscriptionFeature(),
            new MessageFeature(),
            new ExtendedState(),
            new JsonSchemaFeature(),
            new AbilitiesFeature(),
            new PresentationFeature()
        );

        // Setup schema before callbacks run
        $builder->setStates('idle')->build();
        $registry = $builder->chainMail->get(PresentationRegistry::class);
        $registry->setSchemas(['testKey' => ['type' => 'string']]);

        $builder
            ->onEnter('idle', function (object $trigger) use (&$isCallable) {
                // Verify $this->presentation() is callable
                $isCallable = is_callable([$this, 'presentation']);
            });

        $region = $builder->setStates('idle')->build();
        $region->trigger((object)['type' => 'init']);

        $this->assertTrue($isCallable, '$this->presentation() must be callable in state callbacks');
    }
}
