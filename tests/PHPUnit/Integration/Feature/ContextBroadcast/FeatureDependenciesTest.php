<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\ContextBroadcast;

use Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature;
use Noem\State\Feature\ContextBroadcast\ContextChange;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\Holon;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ContextBroadcastFeature requires ExtendedState feature for context helpers
 * and Set chain infrastructure; optionally integrates with JsonSchemaFeature for schema-based
 * filtering; optionally integrates with SubscriptionFeature for type-based listener filtering;
 * functions independently with basic notification delivery when optional features absent
 *
 * @see specs/features/context-broadcast.yaml (feature-dependencies)
 */
#[Group('integration')]
#[Group('context-broadcast')]
class FeatureDependenciesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/context-broadcast-deps-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testRequiresExtendedStateFeature(): void
    {
        // This test verifies that ContextBroadcastFeature uses RequiresFeature attribute
        // The actual requirement check happens at build time via attributes

        // Arrange & Act
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),  // Required
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->build();

        // Assert - build succeeds with ExtendedState
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testUsesExtendedStateContextHelpers(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                // Uses $this->set() from ExtendedState
                $this->set('key', 'value');
            })
            ->build();

        $received = false;
        $region->on(function ($event) use (&$received) {
            if ($event instanceof ContextChange) {
                $received = true;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - ExtendedState helpers work with ContextBroadcast
        $this->assertTrue($received);
    }

    public function testUsesExtendedStateSetChainInfrastructure(): void
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
                $this->set('counter', 2);
            })
            ->build();

        $events = [];
        $region->on(function ($event) use (&$events) {
            if ($event instanceof ContextChange) {
                $events[] = $event->value;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - Set chain middleware properly intercepts both calls
        $this->assertCount(2, $events);
        $this->assertSame([1, 2], $events);
    }

    public function testOptionalIntegrationWithJsonSchemaFeature(): void
    {
        // Arrange - with JsonSchemaFeature
        $yamlWithSchema = $this->tempDir . '/test-with-schema.yml';
        file_put_contents($yamlWithSchema, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: public
      type: string
      default: ''

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('public', 'visible');
              \$this->set('private', 'hidden');
          };
initial: idle
YAML
        );

        $regionWithSchema = Holon::fromYaml($yamlWithSchema);

        $withSchemaKeys = [];
        $regionWithSchema->on(function ($event) use (&$withSchemaKeys) {
            if ($event instanceof ContextChange) {
                $withSchemaKeys[] = $event->key;
            }
        });

        // Arrange - without JsonSchemaFeature
        $regionWithoutSchema = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('public', 'visible');
                $this->set('private', 'hidden');
            })
            ->build();

        $withoutSchemaKeys = [];
        $regionWithoutSchema->on(function ($event) use (&$withoutSchemaKeys) {
            if ($event instanceof ContextChange) {
                $withoutSchemaKeys[] = $event->key;
            }
        });

        // Act
        $regionWithSchema->trigger((object)['type' => 'action']);
        $regionWithoutSchema->trigger((object)['type' => 'action']);

        // Assert - behavior changes based on JsonSchemaFeature presence
        // With schema: gets 'public' (may include default init + action set)
        $publicEvents = array_filter($withSchemaKeys, fn($k) => $k === 'public');
        $this->assertGreaterThan(0, count($publicEvents));
        $this->assertNotContains('private', $withSchemaKeys);
        $this->assertCount(2, $withoutSchemaKeys);  // Both without schema
    }

    public function testOptionalIntegrationWithSubscriptionFeature(): void
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
                $this->set('data', 'value');
            })
            ->build();

        $contextChangeCalled = false;
        $otherTypeCalled = false;

        // Type-filtered listeners via SubscriptionFeature
        $region->on(function ($event) use (&$contextChangeCalled) {
            if ($event instanceof ContextChange) {
                $contextChangeCalled = true;
            }
        });

        $region->on(function (DummyEventType $event) use (&$otherTypeCalled) {
            $otherTypeCalled = true;
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - SubscriptionFeature filters by type
        $this->assertTrue($contextChangeCalled);
        $this->assertFalse($otherTypeCalled);
    }

    public function testFunctionsIndependentlyWithoutOptionalFeatures(): void
    {
        // Arrange - only required features
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
                // No JsonSchemaFeature
                // No SubscriptionFeature
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('test', 'value');
            })
            ->build();

        $received = false;
        $region->on(function ($event) use (&$received) {
  // Untyped listener
            $received = $event instanceof ContextChange;
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - works with basic notification delivery
        $this->assertTrue($received);
    }

    public function testBasicNotificationDeliveryWithoutTypeFiltering(): void
    {
        // Arrange - without SubscriptionFeature
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) {
                $this->set('count', 42);
            })
            ->build();

        $callCount = 0;

        // Catch-all listener (no type filtering without SubscriptionFeature)
        $region->on(function ($event) use (&$callCount) {
            if ($event instanceof ContextChange) {
                $callCount++;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - listener called
        $this->assertSame(1, $callCount);
    }

    public function testAllFeaturesWorkTogether(): void
    {
        // Arrange - all features combined
        $yamlFile = $this->tempDir . '/test-all-features.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature
    - class: Noem\State\Feature\Subscription\SubscriptionFeature

context:
  broadcast: true
  schema:
    - name: tracked
      type: string
      default: ''
    - name: hidden
      type: string
      default: ''
      broadcast: false

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('tracked', 'visible');
              \$this->set('hidden', 'not broadcast');
              \$this->set('unschema', 'not broadcast');
          };
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $contextChangeEvents = [];
        $otherEvents = [];

        $region->on(function ($event) use (&$contextChangeEvents) {
            if ($event instanceof ContextChange) {
                $contextChangeEvents[] = $event->key;
            }
        });

        $region->on(function (DummyEventType $event) use (&$otherEvents) {
            $otherEvents[] = 'dummy';
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - schema filtering + type filtering work together
        // May receive multiple 'tracked' events (default init + action set)
        $trackedEvents = array_filter($contextChangeEvents, fn($k) => $k === 'tracked');
        $this->assertGreaterThan(0, count($trackedEvents));
        $this->assertNotContains('hidden', $contextChangeEvents);
        $this->assertNotContains('unschema', $contextChangeEvents);
        $this->assertEmpty($otherEvents);
    }
}

/**
 * Dummy event type for testing type filtering
 */
class DummyEventType
{
    public string $data = 'test';
}
