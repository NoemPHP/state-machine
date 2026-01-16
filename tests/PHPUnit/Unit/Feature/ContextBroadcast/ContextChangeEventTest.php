<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\ContextBroadcast;

use Noem\State\Feature\ContextBroadcast\ContextChange;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ContextChange event contains path, key, value, previousValue,
 * and timestamp properties; implements JsonSerializable with type field for polymorphic
 * deserialization
 *
 * @see specs/features/context-broadcast.yaml (context-change-event-contract)
 */
#[Group('feature')]
#[Group('context-broadcast')]
class ContextChangeEventTest extends TestCase
{
    public function testEventHasRequiredProperties(): void
    {
        // Arrange
        $timestamp = microtime(true);

        // Act
        $event = new ContextChange(
            path: 'parent/child',
            key: 'counter',
            value: 42,
            previousValue: null,
            timestamp: $timestamp
        );

        // Assert
        $this->assertSame('parent/child', $event->path);
        $this->assertSame('counter', $event->key);
        $this->assertSame(42, $event->value);
        $this->assertNull($event->previousValue);
        $this->assertSame($timestamp, $event->timestamp);
    }

    public function testEventIsReadonly(): void
    {
        // Arrange
        $event = new ContextChange(
            path: 'test',
            key: 'key',
            value: 'value',
            previousValue: 'old',
            timestamp: microtime(true)
        );

        // Assert - readonly properties cannot be modified
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/Cannot modify readonly property/');

        // Act - attempt to modify readonly property
        $event->value = 'new';
    }

    public function testEventImplementsJsonSerializable(): void
    {
        // Arrange
        $event = new ContextChange(
            path: 'root/state',
            key: 'data',
            value: ['foo' => 'bar'],
            previousValue: null,
            timestamp: 1705420800.123
        );

        // Act
        $json = json_encode($event);
        $decoded = json_decode($json, true);

        // Assert
        $this->assertIsString($json);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertSame(ContextChange::class, $decoded['type']);
        $this->assertArrayHasKey('path', $decoded);
        $this->assertArrayHasKey('key', $decoded);
        $this->assertArrayHasKey('value', $decoded);
        $this->assertArrayHasKey('previousValue', $decoded);
        $this->assertArrayHasKey('timestamp', $decoded);
    }

    public function testSerializedEventContainsAllData(): void
    {
        // Arrange
        $timestamp = microtime(true);
        $event = new ContextChange(
            path: 'workflow/processing',
            key: 'progress',
            value: 75,
            previousValue: 50,
            timestamp: $timestamp
        );

        // Act
        $serialized = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($serialized);
        $this->assertSame(ContextChange::class, $serialized['type']);
        $this->assertSame('workflow/processing', $serialized['path']);
        $this->assertSame('progress', $serialized['key']);
        $this->assertSame(75, $serialized['value']);
        $this->assertSame(50, $serialized['previousValue']);
        $this->assertSame($timestamp, $serialized['timestamp']);
    }

    public function testSerializedEventSupportsComplexValues(): void
    {
        // Arrange
        $complexValue = [
            'nested' => ['data' => 'value'],
            'count' => 42,
            'flag' => true,
        ];
        $event = new ContextChange(
            path: 'root',
            key: 'complex',
            value: $complexValue,
            previousValue: null,
            timestamp: microtime(true)
        );

        // Act
        $serialized = $event->jsonSerialize();

        // Assert
        $this->assertSame($complexValue, $serialized['value']);
    }

    public function testTypeFieldEnablesPolymorphicDeserialization(): void
    {
        // Arrange
        $event = new ContextChange(
            path: 'test',
            key: 'test',
            value: 'test',
            previousValue: null,
            timestamp: microtime(true)
        );

        // Act
        $json = json_encode($event);
        $decoded = json_decode($json, true);

        // Assert - type field allows consumer to determine event class
        $this->assertSame(ContextChange::class, $decoded['type']);

        // Simulate polymorphic deserialization logic
        $eventType = $decoded['type'];
        $this->assertTrue(class_exists($eventType));
        $this->assertTrue(is_a($eventType, ContextChange::class, true));
    }
}
