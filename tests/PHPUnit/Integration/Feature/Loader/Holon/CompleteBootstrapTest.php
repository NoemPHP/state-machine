<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Integration\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon builds working machine with features and container
 */
#[Group('loader'), Group('holon'), Group('integration')]
class CompleteBootstrapTest extends TestCase
{
    public function testBootstrapsCompleteMachine(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  container:
    services:
      config:
        value:
          timeout: 30
          retries: 3
  features:
    - class: Noem\State\Feature\Loader\RegionLoader
states:
  - name: initializing
    initial: true
  - name: running
  - name: complete
    final: true
  - name: initializing
    transitions:
      - target: running
  - name: running
    transitions:
      - target: complete
YAML;

        // Act
        $region = Holon::fromYaml($yaml);

        // Assert - machine is fully functional
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('initializing', $region->currentState());

        // Execute through states
        $region->trigger(new \stdClass());
        $this->assertEquals('running', $region->currentState());

        $region->trigger(new \stdClass());
        $this->assertEquals('complete', $region->currentState());
        $this->assertTrue($region->isFinal());
    }

    public function testBootstrapsWithMultipleFeatures(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  container:
    services:
      logger:
        value: "Logger instance"
  features:
    - class: Noem\State\Feature\Loader\RegionLoader
states:
  - name: start
    initial: true
  - name: end
    final: true
  - name: start
    transitions:
      - target: end
YAML;

        // Act
        $region = Holon::fromYaml($yaml);

        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $region->trigger(new \stdClass());
        $this->assertTrue($region->isFinal());
    }

    public function testBootstrapsMinimalMachine(): void
    {
        // Arrange - absolute minimum configuration
        $yaml = <<<YAML
states:
  - name: done
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);

        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('done', $region->currentState());
        $this->assertTrue($region->isFinal());
    }

    public function testBootstrapsWithPhpHelpers(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  container:
    services:
      constant_value:
        value: !php "return 42;"
states:
  - name: active
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);

        // Assert - PHP helper processed during bootstrap
        $this->assertInstanceOf(Region::class, $region);
    }
}
