<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Subscription;

use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Listeners with object typehint match all events
 *
 * @see specs/features/subscription.yaml
 */
#[Group('feature')]
#[Group('subscription')]
class CatchAllTest extends TestCase
{
    public function testObjectTypehintMatchesAllEvents(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new SubscriptionFeature())
            ->setStates('idle')
            ->build();

        $catchAllCalled = 0;

        $region->on(function (object $e) use (&$catchAllCalled) {
            $catchAllCalled++;
        });

        // Act - emit different event types
        $event1 = new \stdClass();
        $event2 = new class {
        };
        $event3 = new \ArrayObject();

        foreach ([$event1, $event2, $event3] as $event) {
            $listeners = $region->notificationChain->call(new Notify($region, $event));
            foreach ($listeners as $listener) {
                $listener($event, $region);
            }
        }

        // Assert - catch-all listener called for all events
        $this->assertEquals(3, $catchAllCalled);
    }

    public function testCatchAllAndSpecificListenersBothReceiveEvent(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new SubscriptionFeature())
            ->setStates('idle')
            ->build();

        $catchAllCalled = false;
        $specificCalled = false;

        $region->on(function (object $e) use (&$catchAllCalled) {
            $catchAllCalled = true;
        });

        $region->on(function (\stdClass $e) use (&$specificCalled) {
            $specificCalled = true;
        });

        // Act
        $listeners = $region->notificationChain->call(new Notify($region, new \stdClass()));
        foreach ($listeners as $listener) {
            $listener(new \stdClass(), $region);
        }

        // Assert - both called
        $this->assertTrue($catchAllCalled);
        $this->assertTrue($specificCalled);
    }
}
