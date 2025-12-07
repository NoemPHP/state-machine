<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: runEventLoop respects maxIterations configuration
 */
#[Group('loader'), Group('holon'), Group('holon-event-loop')]
class MaxIterationsTest extends TestCase
{
    public function testRespectsMaxIterationsLimit(): void
    {
        // Arrange
        $iterationCount = 0;

        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 5
states:
  - name: loop
    initial: true
  - name: exit
    final: true
YAML;

        // Act & Assert - should throw exception when max iterations reached
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum iterations (5)');

        Holon::fromYaml($yaml);
    }

    public function testUsesDefaultMaxIterationsWhenNotSpecified(): void
    {
        // Arrange - machine that will loop indefinitely without max iterations
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: loop
    initial: true
  - name: never_reached
    final: true
YAML;

        // Act & Assert - should eventually hit default limit (10000)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum iterations (10000)');

        Holon::fromYaml($yaml);
    }

    public function testCompletesBeforeMaxIterations(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 100
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

        // Assert - should complete successfully without hitting limit
        $this->assertNotNull($result);
    }

    public function testMaxIterationsAppliesToEachIteration(): void
    {
        // Arrange - configure a very low limit
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 2
states:
  - name: s1
    initial: true
  - name: s2
  - name: s3
  - name: s4
    final: true
  - name: s1
    transitions:
      - target: s2
  - name: s2
    transitions:
      - target: s3
  - name: s3
    transitions:
      - target: s4
YAML;

        // Act & Assert - should fail at 2 iterations (before reaching s4)
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('maximum iterations (2)');

        Holon::fromYaml($yaml);
    }
}
