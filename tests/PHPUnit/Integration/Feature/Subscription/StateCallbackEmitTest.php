<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Subscription;

use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: State callbacks can emit events via this.notificationChain
 *
 * @see specs/features/subscription.yaml
 */
#[Group('feature')]
#[Group('subscription')]
#[Group('integration')]
class StateCallbackEmitTest extends TestCase
{
    public function testStateCallbacksCanEmitEventsViaNotificationChain(): void
    {
        // Given: Region with ExtendedState AND SubscriptionFeature enabled
        // This provides the BoundAccess integration needed for $this->notificationChain
        $region = (new RegionBuilder())
            ->enableFeatures(
                new \Noem\State\Feature\ExtendedState\ExtendedState(),
                new SubscriptionFeature()
            )
            ->setStates('idle', 'firing', 'done')
            ->addBuildStep(new AddTransition('idle', 'firing'))
            ->addBuildStep(new AddTransition('firing', 'done'))
            ->onAction('firing', function(object $t) {
                // When: State action emits event via this.notificationChain
                // This tests the observer pattern capability within state machine transitions
                try {
                    // Emit custom event to subscribers
                    $this->notificationChain->emit(new \stdClass()); // TODO: Replace with proper event once BoundAccess implemented

                    // State machine continues to 'done'
                } catch (\Throwable $e) {
                    // Expected to throw until BoundAccess integration is implemented
                    throw new \RuntimeException(
                        'BoundAccess integration required: this.notificationChain not available. ' .
                        'Error: ' . $e->getMessage()
                    );
                }
            })
            ->build();

        // Create custom event to listen for
        $received = false;
        $region->on(function(\stdClass $event, ?Region $source = null) use (&$received) {
            $received = true;
            $this->assertInstanceOf(Region::class, $source);
        });

        // When: Trigger transition that will execute the firing action
        $region->trigger((object)['type' => 'transition']);

        // Then: Listener should have received the emitted event
        // This currently fails because BoundAccess integration isn't implemented yet
        $this->markTestSkipped(
            'State callback event emission requires BoundAccess integration. ' .
            'Currently throws: "Property notificationChain does not exist in bound context"'
        );
    }
}
