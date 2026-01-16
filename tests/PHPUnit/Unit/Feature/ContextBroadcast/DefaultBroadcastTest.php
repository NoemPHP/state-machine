<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\ContextBroadcast;

use Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature;
use Noem\State\Feature\ContextBroadcast\ContextChange;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: All context changes broadcast when JsonSchemaFeature is not loaded;
 * properties without schema definitions broadcast by default
 *
 * @see specs/features/context-broadcast.yaml (default-broadcast-behavior)
 */
#[Group('feature')]
#[Group('context-broadcast')]
class DefaultBroadcastTest extends TestCase
{
    public function testAllChangesBroadcastWithoutJsonSchemaFeature(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
                // Note: JsonSchemaFeature is NOT loaded
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('property1', 'value1');
                $this->set('property2', 'value2');
                $this->set('property3', 'value3');
            })
            ->build();

        $events = [];
        $region->on(function ($event) use (&$events) {
            if ($event instanceof ContextChange) {
                $events[] = $event->key;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - all properties broadcasted
        $this->assertCount(3, $events);
        $this->assertContains('property1', $events);
        $this->assertContains('property2', $events);
        $this->assertContains('property3', $events);
    }

    public function testArbitraryPropertiesBroadcastWithoutSchema(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                // Set arbitrary properties with no schema definition
                $this->set('randomProperty', 'random');
                $this->set('dynamicData', ['foo' => 'bar']);
                $this->set('internalCache', new \stdClass());
            })
            ->build();

        $broadcastedKeys = [];
        $region->on(function ($event) use (&$broadcastedKeys) {
            if ($event instanceof ContextChange) {
                $broadcastedKeys[] = $event->key;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - all arbitrary properties broadcast
        $this->assertCount(3, $broadcastedKeys);
        $this->assertContains('randomProperty', $broadcastedKeys);
        $this->assertContains('dynamicData', $broadcastedKeys);
        $this->assertContains('internalCache', $broadcastedKeys);
    }

    public function testNoFilteringAppliedByDefault(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('a', 1);
                $this->set('b', 2);
                $this->set('c', 3);
                $this->set('d', 4);
                $this->set('e', 5);
            })
            ->build();

        $eventCount = 0;
        $region->on(function ($event) use (&$eventCount) {
            if ($event instanceof ContextChange) {
                $eventCount++;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - exactly 5 events, no filtering
        $this->assertSame(5, $eventCount);
    }

    public function testDefaultBehaviorWithComplexValues(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('array', ['nested' => ['value']]);
                $this->set('object', (object)['prop' => 'val']);
                $this->set('closure', fn() => 'test');
            })
            ->build();

        $events = [];
        $region->on(function ($event) use (&$events) {
            if ($event instanceof ContextChange) {
                $events[$event->key] = $event->value;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - all complex values broadcast
        $this->assertArrayHasKey('array', $events);
        $this->assertArrayHasKey('object', $events);
        $this->assertArrayHasKey('closure', $events);
        $this->assertIsArray($events['array']);
        $this->assertIsObject($events['object']);
        $this->assertIsCallable($events['closure']);
    }
}
