<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Subscription;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Feature integrates NotificationChain with BoundAccess when ExtendedState enabled
 *
 * @see specs/features/subscription.yaml
 */
#[Group('feature')]
#[Group('subscription')]
#[Group('integration')]
class BoundAccessIntegrationTest extends TestCase
{
    public function testFeatureIntegratesWithBoundAccessWhenExtendedStateEnabled(): void
    {
        // Create test event class
        $eventClass = new class {
            public string $type = 'test';
        };

        $subscriberCalled = false;

        // Given: Region with ExtendedState AND SubscriptionFeature
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new SubscriptionFeature()
            )
            ->setStates('idle', 'processing')
            ->addBuildStep(new AddTransition('idle', 'processing'))
            ->onEnter('processing', function (object $t) use ($eventClass) {
                $this->emit($eventClass);
            })
            ->build();

        // Register a subscriber for our test event
        $region->on(function ($event) use ($eventClass, &$subscriberCalled) {
            if ($event instanceof $eventClass) {
                $subscriberCalled = true;
            }
        });

        // When: Trigger transition to processing state
        $region->trigger((object)['type' => 'transition']);

        // Then: Subscriber should have been called
        $this->assertTrue(
            $subscriberCalled,
            'Subscriber should receive events emitted from state callbacks via BoundAccess integration'
        );
    }
}
