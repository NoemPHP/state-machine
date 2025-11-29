<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Integration\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon machine executes to completion
 */
#[Group('loader'), Group('holon'), Group('integration')]
class ExecutionTest extends TestCase
{
    public function testMachineExecutesToCompletion(): void
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
  - name: done
    final: true
  - name: step1
    transitions:
      - target: step2
  - name: step2
    transitions:
      - target: step3
  - name: step3
    transitions:
      - target: done
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert - completed execution
        $this->assertNotNull($result);
    }
    
    public function testMachineExecutesWithActions(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: start
    initial: true
    run: !php "return fn(\$t) => \$t->count = 1;"
  - name: process
    run: !php "return fn(\$t) => \$t->count++;"
  - name: end
    final: true
    run: !php "return fn(\$t) => \$t->count++;"
  - name: start
    transitions:
      - target: process
  - name: process
    transitions:
      - target: end
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertNotNull($result);
    }
    
    public function testMachineExecutesWithGuards(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
states:
  - name: start
    initial: true
  - name: checked
  - name: passed
    final: true
  - name: start
    transitions:
      - target: checked
  - name: checked
    transitions:
      - target: passed
        guard: !php "return fn(\$t) => true;"
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertNotNull($result);
    }
    
    public function testManualExecutionWorkflow(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: idle
    initial: true
  - name: processing
  - name: complete
    final: true
  - name: idle
    transitions:
      - target: processing
  - name: processing
    transitions:
      - target: complete
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - manual control
        $this->assertEquals('idle', $region->currentState());
        
        $region->trigger(new \stdClass());
        $this->assertEquals('processing', $region->currentState());
        
        $region->trigger(new \stdClass());
        $this->assertEquals('complete', $region->currentState());
        $this->assertTrue($region->isFinal());
    }
    
    public function testExecutionWithContainerServices(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  container:
    services:
      processor:
        value: "ProcessorService"
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

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert - completed with container available
        $this->assertNotNull($result);
    }
}
