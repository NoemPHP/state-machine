<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon returns Region when autoRun is false
 */
#[Group('loader'), Group('holon'), Group('holon-event-loop')]
class ManualControlTest extends TestCase
{
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
        
        // Assert
        $this->assertInstanceOf(Region::class, $result);
    }
    
    public function testRegionAllowsManualExecution(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: false
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
        $region = Holon::fromYaml($yaml);
        
        // Assert - initial state
        $this->assertEquals('start', $region->currentState());
        $this->assertFalse($region->isFinal());
        
        // Manual control - trigger first transition
        $region->trigger(new \stdClass());
        $this->assertEquals('middle', $region->currentState());
        
        // Manual control - trigger second transition
        $region->trigger(new \stdClass());
        $this->assertEquals('end', $region->currentState());
        $this->assertTrue($region->isFinal());
    }
    
    public function testDefaultBehaviorIsManualControl(): void
    {
        // Arrange - no eventLoop config at all
        $yaml = <<<YAML
states:
  - name: active
    initial: true
    final: true
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert - should return Region by default
        $this->assertInstanceOf(Region::class, $result);
    }
    
    public function testManualControlWithNoEventLoopConfig(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: s1
    initial: true
  - name: s2
    final: true
  - name: s1
    transitions:
      - target: s2
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('s1', $region->currentState());
        
        // Verify manual control works
        $region->trigger(new \stdClass());
        $this->assertEquals('s2', $region->currentState());
    }
    
    public function testExplicitAutoRunFalse(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: false
    maxIterations: 5
states:
  - name: start
    initial: true
  - name: end
    final: true
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert - maxIterations should be ignored when autoRun is false
        $this->assertInstanceOf(Region::class, $result);
        $this->assertEquals('start', $result->currentState());
    }
}
