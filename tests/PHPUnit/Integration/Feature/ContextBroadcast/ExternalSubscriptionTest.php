<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\ContextBroadcast;

use Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature;
use Noem\State\Feature\ContextBroadcast\ContextChange;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: External code subscribes to ContextChange events via $region->on();
 * listeners receive event and source region parameters; returns deregister callable for cleanup;
 * supports multiple listeners and type-based filtering via SubscriptionFeature
 *
 * @see specs/features/context-broadcast.yaml (external-subscription)
 */
#[Group('integration')]
#[Group('context-broadcast')]
class ExternalSubscriptionTest extends TestCase
{
    public function testSubscribeViaRegionOn(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('key', 'value');
            })
            ->build();

        $received = false;
        $deregister = $region->on(function ($event) use (&$received) {
            if ($event instanceof ContextChange) {
                $received = true;
            }
        });

        // Assert - returns callable
        $this->assertIsCallable($deregister);

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert
        $this->assertTrue($received);
    }

    public function testListenerReceivesEventAndRegionParameters(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('processing')
            ->markInitial('processing')
            ->onAction('processing', function (object $t) {
                $this->set('data', 123);
            })
            ->build();

        $receivedEvent = null;
        $receivedRegion = null;

        $region->on(function ($event, $source) use (&$receivedEvent, &$receivedRegion) {
            if ($event instanceof ContextChange) {
                $receivedEvent = $event;
                $receivedRegion = $source;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert
        $this->assertInstanceOf(ContextChange::class, $receivedEvent);
        $this->assertSame($region, $receivedRegion);
    }

    public function testDeregisterRemovesListener(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('counter', 1);
            })
            ->build();

        $callCount = 0;
        $deregister = $region->on(function ($event) use (&$callCount) {
            if ($event instanceof ContextChange) {
                $callCount++;
            }
        });

        // Act - run once with listener
        $region->trigger((object)['type' => 'action']);
        $initialCount = $callCount;
        $this->assertGreaterThan(0, $initialCount);

        // Deregister
        $deregister();

        // Trigger another set
        $region->trigger((object)['type' => 'action']);

        // Assert - listener not called after deregister
        $this->assertSame($initialCount, $callCount);
    }

    public function testSupportsMultipleListeners(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('test', 'value');
            })
            ->build();

        $listener1Called = false;
        $listener2Called = false;
        $listener3Called = false;

        $region->on(function ($event) use (&$listener1Called) {
            if ($event instanceof ContextChange) {
                $listener1Called = true;
            }
        });

        $region->on(function ($event) use (&$listener2Called) {
            if ($event instanceof ContextChange) {
                $listener2Called = true;
            }
        });

        $region->on(function ($event) use (&$listener3Called) {
            if ($event instanceof ContextChange) {
                $listener3Called = true;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - all listeners called
        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
        $this->assertTrue($listener3Called);
    }

    public function testTypeBasedFilteringWithSubscriptionFeature(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature(),
                new SubscriptionFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('value', 42);
            })
            ->build();

        $contextChangeCalled = false;
        $otherEventCalled = false;

        // Subscribe to ContextChange events
        $region->on(function ($event) use (&$contextChangeCalled) {
            if ($event instanceof ContextChange) {
                $contextChangeCalled = true;
            }
        });

        // Subscribe to different event type (should not be called)
        $region->on(function (DummyEvent $event) use (&$otherEventCalled) {
            $otherEventCalled = true;
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - only ContextChange listener called
        $this->assertTrue($contextChangeCalled);
        $this->assertFalse($otherEventCalled);
    }

    public function testMultipleListenersReceiveIndependentState(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('counter', 100);
            })
            ->build();

        $listener1Value = null;
        $listener2Value = null;

        $region->on(function ($event) use (&$listener1Value) {
            if ($event instanceof ContextChange) {
                $listener1Value = $event->value;
            }
        });

        $region->on(function ($event) use (&$listener2Value) {
            if ($event instanceof ContextChange) {
                $listener2Value = $event->value;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - both received the same value independently
        $this->assertSame(100, $listener1Value);
        $this->assertSame(100, $listener2Value);
    }

    public function testDeregisterOneListenerDoesNotAffectOthers(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('test', 'value');
            })
            ->build();

        $listener1Count = 0;
        $listener2Count = 0;

        $deregister1 = $region->on(function ($event) use (&$listener1Count) {
            if ($event instanceof ContextChange) {
                $listener1Count++;
            }
        });

        $region->on(function ($event) use (&$listener2Count) {
            if ($event instanceof ContextChange) {
                $listener2Count++;
            }
        });

        // Act - first run
        $region->trigger((object)['type' => 'action']);
        $this->assertSame(1, $listener1Count);
        $this->assertSame(1, $listener2Count);

        // Deregister first listener
        $deregister1();

        // Trigger another change
        $region->trigger((object)['type' => 'action']);

        // Assert - listener 1 not called, listener 2 still active
        $this->assertSame(1, $listener1Count);
        $this->assertSame(2, $listener2Count);
    }
}

/**
 * Dummy event class for testing type filtering
 */
class DummyEvent
{
    public string $data = 'dummy';
}
