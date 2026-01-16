<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\ContextBroadcast;

use Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature;
use Noem\State\Feature\ContextBroadcast\ContextChange;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\Holon;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Only schema-defined properties broadcast when JsonSchemaFeature is loaded;
 * non-schema properties do not emit ContextChange events
 *
 * @see specs/features/context-broadcast.yaml (schema-based-filtering)
 */
#[Group('integration')]
#[Group('context-broadcast')]
class SchemaBasedFilteringTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/context-broadcast-schema-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }
    public function testOnlySchemaPropertiesBroadcast(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-schema-properties.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: progress
      type: integer
      default: 0
    - name: status
      type: string
      default: idle

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('progress', 50);
              \$this->set('status', 'active');
              \$this->set('internalCache', []);
          };
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $broadcastedKeys = [];
        $region->on(function ($event) use (&$broadcastedKeys) {
            if ($event instanceof ContextChange) {
                $broadcastedKeys[] = $event->key;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - only schema properties broadcast
        $this->assertCount(2, $broadcastedKeys);
        $this->assertContains('progress', $broadcastedKeys);
        $this->assertContains('status', $broadcastedKeys);
        $this->assertNotContains('internalCache', $broadcastedKeys);
    }

    public function testNonSchemaPropertiesDoNotEmitEvents(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-non-schema.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: publicData
      type: string
      default: ''

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('publicData', 'visible');
              \$this->set('privateData', 'hidden');
              \$this->set('temporaryCache', 'temp');
          };
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $eventCount = 0;
        $region->on(function ($event) use (&$eventCount) {
            if ($event instanceof ContextChange) {
                $eventCount++;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - only 1 event for schema property
        $this->assertSame(1, $eventCount);
    }

    public function testSchemaDefinitionTreatedAsPublicAPI(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-public-api.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: userId
      type: integer
    - name: userName
      type: string

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('userId', 123);
              \$this->set('userName', 'John');
              \$this->set('sessionToken', 'abc123');
              \$this->set('cacheKey', 'key');
          };
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $events = [];
        $region->on(function ($event) use (&$events) {
            if ($event instanceof ContextChange) {
                $events[$event->key] = $event->value;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - only public API properties broadcast
        $this->assertArrayHasKey('userId', $events);
        $this->assertArrayHasKey('userName', $events);
        $this->assertArrayNotHasKey('sessionToken', $events);
        $this->assertArrayNotHasKey('cacheKey', $events);
    }

    public function testPreventsNonSerializableObjectBroadcasts(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-serializable.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: count
      type: integer
      default: 0

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('count', 10);
              \$this->set('closure', fn() => 'test');
              \$this->set('resource', fopen('php://memory', 'r'));
          };
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $broadcastedKeys = [];
        $region->on(function ($event) use (&$broadcastedKeys) {
            if ($event instanceof ContextChange) {
                $broadcastedKeys[] = $event->key;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - only schema property broadcast (avoids serialization errors)
        $this->assertCount(1, $broadcastedKeys);
        $this->assertContains('count', $broadcastedKeys);
        $this->assertNotContains('closure', $broadcastedKeys);
        $this->assertNotContains('resource', $broadcastedKeys);
    }

    public function testEmptySchemaAllowsNoBroadcasts(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-empty-schema.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema: []

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('property1', 'value1');
              \$this->set('property2', 'value2');
          };
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $eventCount = 0;
        $region->on(function ($event) use (&$eventCount) {
            if ($event instanceof ContextChange) {
                $eventCount++;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - no broadcasts when schema is empty
        $this->assertSame(0, $eventCount);
    }

    public function testSchemaFilteringIndependentOfPropertyOrder(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-property-order.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: second
      type: string
    - name: fourth
      type: string

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('first', 'a');
              \$this->set('second', 'b');
              \$this->set('third', 'c');
              \$this->set('fourth', 'd');
          };
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $broadcastedKeys = [];
        $region->on(function ($event) use (&$broadcastedKeys) {
            if ($event instanceof ContextChange) {
                $broadcastedKeys[] = $event->key;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - order doesn't matter, only schema membership
        $this->assertCount(2, $broadcastedKeys);
        $this->assertContains('second', $broadcastedKeys);
        $this->assertContains('fourth', $broadcastedKeys);
    }
}
