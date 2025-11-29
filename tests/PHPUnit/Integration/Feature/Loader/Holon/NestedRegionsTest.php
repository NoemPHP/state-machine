<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Integration\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon supports complex configurations with nested regions
 */
#[Group('loader'), Group('holon'), Group('integration')]
class NestedRegionsTest extends TestCase
{
    public function testSupportsNestedRegions(): void
    {
        // Arrange
        // language=yaml
        $yaml = <<<YAML
states:
  - name: parent
    initial: true
    regions:
      - states:
          - name: child1
            initial: true
            final: true
    transitions:
      - target: done
  - name: done
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('parent', $region->currentState());
    }
    
    public function testExecutesHierarchicalMachine(): void
    {
        // Arrange
        // language=yaml
        $yaml = <<<YAML
states:
  - name: level1
    initial: true
    regions:
      - states:
          - name: level2a
            initial: true
            transitions:
              - target: level2b
          - name: level2b
            final: true
    transitions:
      - target: complete
  - name: complete
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertEquals('level1', $region->currentState());
        
        // Trigger to complete nested region
        $region->trigger(new \stdClass());
        
        // Trigger to move to complete state
        $region->trigger(new \stdClass());
        $this->assertEquals('complete', $region->currentState());
        $this->assertTrue($region->isFinal());
    }
    
    public function testMultipleNestedRegions(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: parallel
    initial: true
    regions:
      - states:
          - name: branch_a
            initial: true
            final: true
      - states:
          - name: branch_b
            initial: true
            final: true
    transitions:
      - target: merged
  - name: merged
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('parallel', $region->currentState());
    }
    
    public function testDeeplyNestedHierarchy(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: root
    initial: true
    regions:
      - states:
          - name: mid
            initial: true
            regions:
              - states:
                  - name: leaf
                    initial: true
                    final: true
            final: true
    transitions:
      - target: end
  - name: end
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('root', $region->currentState());
    }
    
    public function testNestedRegionsWithAutoRun(): void
    {
        // Arrange
        // language=yaml
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: true
    trigger: !php |
      \$trigger = null;
      return function(\$iteration, \$region, \$container) use (&\$trigger) {
          if (\$trigger === null) {
              \$trigger = new stdClass();
          }
          return \$trigger;
      };
states:
  - name: container
    initial: true
    action:
      #language=injectablephp
      - run: !php |
          return function(\$t) {
            if (!isset(\$t->visitedStates)) {
              \$t->visitedStates = [];
            }
            \$t->visitedStates[] = 'container';
          };
    regions:
      - states:
          - name: nested
            initial: true
            action:
              - run: !php |
                  return function(\$t) {
                    if (!isset(\$t->visitedStates)) {
                      \$t->visitedStates = [];
                    }
                    \$t->visitedStates[] = 'nested';
                  };
            transitions:
              - target: nested_end
          - name: nested_end
            final: true
            action:
              - run: !php |
                  return function(\$t) {
                    if (!isset(\$t->visitedStates)) {
                      \$t->visitedStates = [];
                    }
                    \$t->visitedStates[] = 'nested_end';
                  };
    transitions:
      - target: final
  - name: final
    final: true
    action:
      - run: !php |
          return function(\$t) {
            if (!isset(\$t->visitedStates)) {
              \$t->visitedStates = [];
            }
            \$t->visitedStates[] = 'final';
          };
YAML;

        // Act
        $result = Holon::fromYaml($yaml);

        // Assert - Verify the action callbacks modified the trigger during execution
        $this->assertNotNull($result);
        $this->assertIsObject($result);
        $this->assertIsArray($result->visitedStates);
        $this->assertNotEmpty($result->visitedStates, 'Action callbacks should have modified trigger during autoRun');
        $this->assertContains('container', $result->visitedStates, 'Should have entered container state');
        $this->assertContains('nested', $result->visitedStates, 'Should have entered nested state');
    }
}
