<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Acceptance Criterion: runEventLoop throws RuntimeException when max iterations reached
 */
#[Group('loader'), Group('holon'), Group('holon-event-loop')]
class MaxIterationsErrorTest extends TestCase
{
    public function testThrowsRuntimeExceptionOnMaxIterations(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 3
states:
  - name: infinite_loop
    initial: true
  - name: never_reached
    final: true
YAML;

        // Assert
        $this->expectException(RuntimeException::class);
        
        // Act
        Holon::fromYaml($yaml);
    }
    
    public function testExceptionMessageIncludesIterationCount(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 7
states:
  - name: loop
    initial: true
  - name: never_reached
    final: true
YAML;

        // Act & Assert
        try {
            Holon::fromYaml($yaml);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('7', $e->getMessage());
            $this->assertStringContainsString('maximum iterations', $e->getMessage());
        }
    }
    
    public function testExceptionIndicatesEventLoopContext(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 1
states:
  - name: start
    initial: true
  - name: end
    final: true
YAML;

        // Act & Assert
        try {
            Holon::fromYaml($yaml);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Event loop', $e->getMessage());
        }
    }
    
    public function testNoExceptionWhenMachineCompletesNormally(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 10
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
        
        // Assert - no exception thrown
        $this->assertNotNull($result);
    }
}
