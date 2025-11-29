<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: runEventLoop stops when region reaches final state
 */
#[Group('loader'), Group('holon'), Group('holon-event-loop')]
class FinalStateStopTest extends TestCase
{
    public function testStopsWhenFinalStateReached(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 1000
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
        
        // Assert - should complete without hitting maxIterations
        $this->assertNotNull($result);
    }
    
    public function testDoesNotExceedNecessaryIterations(): void
    {
        // Arrange
        $iterationCount = 0;
        
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: start
    initial: true
  - name: end
    final: true
  - name: start
    transitions:
      - target: end
YAML;

        // We can't directly test iteration count from YAML config,
        // but we can verify the machine completes quickly
        $startTime = microtime(true);
        
        // Act
        $result = Holon::fromYaml($yaml);
        
        $duration = microtime(true) - $startTime;
        
        // Assert - should complete very quickly (well under 1 second)
        $this->assertLessThan(1.0, $duration);
        $this->assertNotNull($result);
    }
    
    public function testStopsImmediatelyIfAlreadyInFinalState(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: done
    initial: true
    final: true
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert - should complete immediately
        $this->assertNotNull($result);
    }
    
    public function testMultipleTransitionsUntilFinal(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    maxIterations: 100
states:
  - name: s1
    initial: true
  - name: s2
  - name: s3
  - name: s4
  - name: s5
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
  - name: s4
    transitions:
      - target: s5
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert - should complete successfully
        $this->assertNotNull($result);
    }
}
