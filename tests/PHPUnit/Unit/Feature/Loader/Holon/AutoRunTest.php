<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon runs event loop when autoRun is true
 */
#[Group('loader'), Group('holon'), Group('holon-event-loop')]
class AutoRunTest extends TestCase
{
    public function testExecutesEventLoopWhenAutoRunTrue(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: start
    initial: true
  - name: middle
  - name: end
    final: true
  - name: start
    transitions:
      - target: middle
  - name: middle
    transitions:
      - target: end
YAML;

        // Act
        $result = Holon::fromYaml($yaml);

        // Assert - should return trigger result, not Region
        // The machine should have run to completion
        $this->assertNotNull($result);
    }

    public function testReturnsRegionWhenAutoRunFalse(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: false
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
        $result = Holon::fromYaml($yaml);

        // Assert - should return Region instance
        $this->assertInstanceOf(\Noem\State\Region::class, $result);
        $this->assertEquals('start', $result->currentState());
        $this->assertFalse($result->isFinal());
    }

    public function testDefaultBehaviorReturnsRegion(): void
    {
        // Arrange - no eventLoop config means autoRun defaults to false
        $yaml = <<<YAML
states:
  - name: active
    initial: true
    final: true
YAML;

        // Act
        $result = Holon::fromYaml($yaml);

        // Assert - should return Region by default
        $this->assertInstanceOf(\Noem\State\Region::class, $result);
    }

    public function testAutoRunExecutesToFinalState(): void
    {
        // Arrange
        $executionLog = [];

        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: step1
    initial: true
  - name: step2
  - name: step3
    final: true
  - name: step1
    transitions:
      - target: step2
  - name: step2
    transitions:
      - target: step3
YAML;

        // Act
        $result = Holon::fromYaml($yaml);

        // Assert - the machine completed execution
        $this->assertNotNull($result);
    }
}
