<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\ContextBroadcast;

use Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature;
use Noem\State\Feature\ContextBroadcast\ContextChange;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YAML schema accepts optional broadcast boolean flag per property;
 * schema properties with broadcast false do not emit ContextChange events; properties default
 * to broadcast true when flag omitted
 *
 * @see specs/features/context-broadcast.yaml (property-level-opt-out)
 */
#[Group('integration')]
#[Group('context-broadcast')]
class PropertyOptOutTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/context-broadcast-property-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testSchemaAcceptsBroadcastFlag(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-broadcast-flag.yml';
        file_put_contents($yamlFile, <<<YAML
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
      broadcast: true
    - name: private
      type: string
      broadcast: false

states:
  - name: idle
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        // Assert - build succeeds with broadcast flag
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testBroadcastFalsePreventsContextChangeEvents(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-broadcast-false.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: visible
      type: string
      broadcast: true
    - name: hidden
      type: string
      broadcast: false

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('visible', 'broadcasted');
              \$this->set('hidden', 'not broadcasted');
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

        // Assert - only 'visible' broadcast
        $this->assertCount(1, $broadcastedKeys);
        $this->assertContains('visible', $broadcastedKeys);
        $this->assertNotContains('hidden', $broadcastedKeys);
    }

    public function testPropertiesDefaultToBroadcastTrue(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-default-broadcast.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: property1
      type: string
    - name: property2
      type: string

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

        $broadcastedKeys = [];
        $region->on(function ($event) use (&$broadcastedKeys) {
            if ($event instanceof ContextChange) {
                $broadcastedKeys[] = $event->key;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - both broadcast by default
        $this->assertCount(2, $broadcastedKeys);
        $this->assertContains('property1', $broadcastedKeys);
        $this->assertContains('property2', $broadcastedKeys);
    }

    public function testSensitiveDataOptOut(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-sensitive-data.yml';
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
    - name: userToken
      type: string
      broadcast: false
    - name: password
      type: string
      broadcast: false

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('userId', 123);
              \$this->set('userToken', 'secret-token');
              \$this->set('password', 'secret-pass');
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

        // Assert - sensitive data not broadcast
        $this->assertCount(1, $broadcastedKeys);
        $this->assertContains('userId', $broadcastedKeys);
        $this->assertNotContains('userToken', $broadcastedKeys);
        $this->assertNotContains('password', $broadcastedKeys);
    }

    public function testComplexObjectOptOut(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-complex-object.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: simpleData
      type: string
    - name: complexObject
      type: object
      broadcast: false

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('simpleData', 'easy to serialize');
              \$this->set('complexObject', (object)['nested' => ['data' => 'hard to serialize']]);
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

        // Assert - complex object not broadcast
        $this->assertCount(1, $broadcastedKeys);
        $this->assertContains('simpleData', $broadcastedKeys);
        $this->assertNotContains('complexObject', $broadcastedKeys);
    }

    public function testMixedBroadcastFlags(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-mixed-flags.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: a
      type: string
      broadcast: true
    - name: b
      type: string
      broadcast: false
    - name: c
      type: string
    - name: d
      type: string
      broadcast: false
    - name: e
      type: string
      broadcast: true

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('a', '1');
              \$this->set('b', '2');
              \$this->set('c', '3');
              \$this->set('d', '4');
              \$this->set('e', '5');
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

        // Assert - only true and default properties broadcast
        $this->assertCount(3, $broadcastedKeys);
        $this->assertContains('a', $broadcastedKeys);
        $this->assertContains('c', $broadcastedKeys);
        $this->assertContains('e', $broadcastedKeys);
        $this->assertNotContains('b', $broadcastedKeys);
        $this->assertNotContains('d', $broadcastedKeys);
    }

    public function testPerformanceOptOutForHighFrequencyChanges(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/test-performance.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: result
      type: integer
    - name: internalCounter
      type: integer
      broadcast: false

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              for (\$i = 0; \$i < 100; \$i++) {
                  \$this->set('internalCounter', \$i);
              }
              \$this->set('result', 100);
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

        // Assert - only 1 event for 'result', 100 internal updates not broadcast
        $this->assertSame(1, $eventCount);
    }
}
