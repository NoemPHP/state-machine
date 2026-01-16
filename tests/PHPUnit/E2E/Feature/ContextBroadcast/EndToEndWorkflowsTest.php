<?php

declare(strict_types=1);

namespace Noem\State\Tests\E2E\Feature\ContextBroadcast;

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
 * Acceptance Criterion: Complete workflow from $this->set() to listener invocation with
 * ContextChange delivery; schema-based filtering with JsonSchemaFeature excluding non-schema
 * properties; region-level opt-out preventing all broadcasts; property-level opt-out for
 * sensitive data; multiple simultaneous listeners receiving distinct events
 *
 * @see specs/features/context-broadcast.yaml (end-to-end-workflows)
 */
#[Group('e2e')]
#[Group('context-broadcast')]
class EndToEndWorkflowsTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/context-broadcast-e2e-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testCompleteWorkflowFromSetToListenerInvocation(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('active')
            ->markInitial('active')
            ->onAction('active', function (object $t) {
                $this->set('status', 'initialized');
                $counter = $this->get('counter', 0);
                $this->set('counter', $counter + 1);
                $this->set('status', 'processing');
                $this->set('status', 'completed');
            })
            ->build();

        $receivedEvents = [];
        $region->on(function ($event, $source) use (&$receivedEvents, $region) {
            if ($event instanceof ContextChange) {
                $receivedEvents[] = [
                    'path' => $event->path,
                    'key' => $event->key,
                    'value' => $event->value,
                    'previousValue' => $event->previousValue,
                    'hasTimestamp' => $event->timestamp > 0,
                    'sourceIsCorrect' => $source === $region,
                ];
            }
        });

        // Act - execute complete workflow
        $region->trigger((object)['type' => 'action']);

        // Assert - complete workflow tracked
        $this->assertGreaterThan(0, count($receivedEvents));

        // Verify first event
        $firstEvent = $receivedEvents[0];
        $this->assertSame('status', $firstEvent['key']);
        $this->assertSame('initialized', $firstEvent['value']);
        $this->assertTrue($firstEvent['hasTimestamp']);
        $this->assertTrue($firstEvent['sourceIsCorrect']);
    }

    public function testSchemaBasedFilteringExcludesNonSchemaProperties(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/schema-filtering-e2e.yml';
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
      default: 0
    - name: userName
      type: string
      default: ''

states:
  - name: authenticated
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('userId', 123);
              \$this->set('userName', 'Alice');
              \$this->set('sessionToken', 'abc123');
              \$this->set('lastRequest', time());
              \$this->set('connectionPool', new \stdClass());
          };
initial: authenticated
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

        // Assert - only schema properties broadcast (excluding default initialization)
        $userIdEvents = array_filter($broadcastedKeys, fn($k) => $k === 'userId');
        $userNameEvents = array_filter($broadcastedKeys, fn($k) => $k === 'userName');
        $this->assertGreaterThan(0, count($userIdEvents));
        $this->assertGreaterThan(0, count($userNameEvents));
        $this->assertNotContains('sessionToken', $broadcastedKeys);
        $this->assertNotContains('lastRequest', $broadcastedKeys);
        $this->assertNotContains('connectionPool', $broadcastedKeys);
    }

    public function testRegionLevelOptOutPreventsAllBroadcasts(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/region-opt-out-e2e.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: false
  schema:
    - name: counter
      type: integer
      default: 0
    - name: status
      type: string
      default: ""

states:
  - name: active
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('counter', 1);
              \$this->set('status', 'running');
              \$this->set('internalData', 'hidden');
          };
initial: active
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

        // Assert - no broadcasts despite multiple sets
        $this->assertSame(0, $eventCount);
    }

    public function testPropertyLevelOptOutForSensitiveData(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/property-opt-out-e2e.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true
  schema:
    - name: publicUserId
      type: integer
      default: 0
    - name: apiKey
      type: string
      default: ''
      broadcast: false
    - name: password
      type: string
      default: ''
      broadcast: false
    - name: email
      type: string
      default: ''

states:
  - name: login
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('publicUserId', 42);
              \$this->set('apiKey', 'secret-key-12345');
              \$this->set('password', 'super-secret');
              \$this->set('email', 'user@example.com');
          };
initial: login
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $broadcastedData = [];
        $region->on(function ($event) use (&$broadcastedData) {
            if ($event instanceof ContextChange) {
                $broadcastedData[$event->key] = $event->value;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - sensitive data not broadcast
        $this->assertArrayHasKey('publicUserId', $broadcastedData);
        $this->assertArrayHasKey('email', $broadcastedData);
        $this->assertArrayNotHasKey('apiKey', $broadcastedData);
        $this->assertArrayNotHasKey('password', $broadcastedData);
    }

    public function testMultipleSimultaneousListenersReceiveDistinctEvents(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new ExtendedState(),
                new ContextBroadcastFeature()
            )
            ->setStates('counter')
            ->markInitial('counter')
            ->onAction('counter', function (object $t) {
                for ($i = 0; $i < 5; $i++) {
                    $this->set('count', $i);
                }
            })
            ->build();

        $listener1Events = [];
        $listener2Events = [];
        $listener3Events = [];

        // Three independent listeners
        $region->on(function ($event) use (&$listener1Events) {
            if ($event instanceof ContextChange && $event->key === 'count') {
                $listener1Events[] = $event->value;
            }
        });

        $region->on(function ($event) use (&$listener2Events) {
            if ($event instanceof ContextChange && $event->key === 'count') {
                $listener2Events[] = $event->value;
            }
        });

        $region->on(function ($event) use (&$listener3Events) {
            if ($event instanceof ContextChange && $event->key === 'count') {
                $listener3Events[] = $event->value;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - all listeners receive all events independently
        $this->assertSame([0, 1, 2, 3, 4], $listener1Events);
        $this->assertSame([0, 1, 2, 3, 4], $listener2Events);
        $this->assertSame([0, 1, 2, 3, 4], $listener3Events);
    }

    public function testComplexWorkflowWithAllFeatures(): void
    {
        // Arrange - complete real-world scenario
        $yamlFile = $this->tempDir . '/complex-workflow.yml';
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
    - name: taskId
      type: integer
    - name: progress
      type: integer
      default: 0
    - name: status
      type: string
      default: "pending"
    - name: error
      type: string
      default: ""
    - name: apiToken
      type: string
      broadcast: false

states:
  - name: idle

  - name: processing
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('taskId', 101);
              \$this->set('status', 'processing');
              \$this->set('progress', 0);
              \$this->set('apiToken', 'secret-token');
              for (\$i = 0; \$i <= 100; \$i += 25) {
                  \$this->set('progress', \$i);
              }
          };

  - name: completed
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('status', 'completed');
              \$this->set('progress', 100);
          };

  - name: error
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('status', 'error');
              \$this->set('error', 'Something went wrong');
          };
initial: processing
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $timeline = [];
        $region->on(function ($event) use (&$timeline) {
            if ($event instanceof ContextChange) {
                $timeline[] = [
                    'key' => $event->key,
                    'value' => $event->value,
                    'path' => $event->path,
                ];
            }
        });

        // Act - execute workflow
        $region->trigger((object)['type' => 'action']);

        // Assert - complete timeline captured
        $this->assertGreaterThan(0, count($timeline));

        // Verify no sensitive data broadcast
        $keys = array_column($timeline, 'key');
        $this->assertNotContains('apiToken', $keys);

        // Verify public data broadcast
        $this->assertContains('taskId', $keys);
        $this->assertContains('status', $keys);
        $this->assertContains('progress', $keys);

        // Verify progress updates
        $progressValues = array_filter($timeline, fn($e) => $e['key'] === 'progress');
        $this->assertGreaterThan(1, count($progressValues));
    }
}
