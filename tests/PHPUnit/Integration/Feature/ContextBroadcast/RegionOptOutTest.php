<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\ContextBroadcast;

use Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature;
use Noem\State\Feature\ContextBroadcast\ContextChange;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\JsonSchema\AddJsonSchema;
use Noem\State\Feature\JsonSchema\JsonSchemaFeature;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: YAML context.broadcast boolean flag controls region-wide broadcasting;
 * no ContextChange events emitted when context.broadcast is false; defaults to true when omitted;
 * region-level false overrides property-level true
 *
 * @see specs/features/context-broadcast.yaml (region-level-opt-out)
 */
#[Group('integration')]
#[Group('context-broadcast')]
class RegionOptOutTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/context-broadcast-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testRegionBroadcastFalseDisablesAllBroadcasts(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/region-opt-out.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: false
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

        $eventCount = 0;
        $region->on(function ($event) use (&$eventCount) {
            if ($event instanceof ContextChange) {
                $eventCount++;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - no broadcasts when context.broadcast is false
        $this->assertSame(0, $eventCount);
    }

    public function testRegionBroadcastDefaultsToTrue(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/region-default.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  # broadcast flag omitted - defaults to true
  schema:
    - name: test
      type: string

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('test', 'value');
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

        // Assert - broadcasts by default
        $this->assertSame(1, $eventCount);
    }

    public function testRegionOptOutOverridesPropertyOptIn(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/region-override.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: false  # Region-level: disabled
  schema:
    - name: explicitTrue
      type: string
      broadcast: true  # Property-level: enabled (but region overrides)
    - name: explicitFalse
      type: string
      broadcast: false
    - name: defaultTrue
      type: string

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('explicitTrue', 'a');
              \$this->set('explicitFalse', 'b');
              \$this->set('defaultTrue', 'c');
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

        // Assert - no broadcasts despite property-level settings
        $this->assertSame(0, $eventCount);
    }

    public function testInternalRegionPerformanceOptimization(): void
    {
        // Arrange - internal helper region with high-frequency changes
        $yamlFile = $this->tempDir . '/internal-region.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: false  # Internal region - no external observability needed
  schema:
    - name: counter
      type: integer
      default: 0

states:
  - name: counting
    onEnter:
      - run: !php |
          return function(object \$t) {
              for (\$i = 0; \$i < 1000; \$i++) {
                  \$this->set('counter', \$i);
              }
          };
initial: counting
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

        // Assert - no events emitted for 1000 changes
        $this->assertSame(0, $eventCount);
    }

    public function testRegionBroadcastTrueAllowsBroadcasts(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/region-enabled.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\JsonSchema\JsonSchemaFeature
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: true  # Explicitly enabled
  schema:
    - name: data
      type: string

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('data', 'broadcasted');
          };
initial: idle
YAML
        );

        $region = Holon::fromYaml($yamlFile);

        $received = false;
        $region->on(function ($event) use (&$received) {
            if ($event instanceof ContextChange) {
                $received = true;
            }
        });

        // Act
        $region->trigger((object)['type' => 'action']);

        // Assert - broadcast allowed when explicitly true
        $this->assertTrue($received);
    }

    public function testRegionOptOutWithoutSchema(): void
    {
        // Arrange
        $yamlFile = $this->tempDir . '/region-no-schema.yml';
        file_put_contents($yamlFile, <<<YAML
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature

context:
  broadcast: false

states:
  - name: idle
    onEnter:
      - run: !php |
          return function(object \$t) {
              \$this->set('anyProperty', 'value');
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

        // Assert - no broadcasts even without schema
        $this->assertSame(0, $eventCount);
    }
}
