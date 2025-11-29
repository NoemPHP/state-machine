<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Parsed configuration includes machine, states, and regions sections
 */
#[Group('loader'), Group('holon'), Group('holon-bootstrapping')]
class ConfigurationStructureTest extends TestCase
{
    public function testSupportsMachineSection(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\Transitions\TransitionsFeature
  container:
    services:
      test:
        value: 123
states:
  - name: active
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testSupportsStatesSection(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: first
    initial: true
  - name: second
  - name: third
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('first', $region->currentState());
    }
    
    public function testSupportsRegionsSection(): void
    {
        // Arrange - Nested regions
        $yaml = <<<YAML
states:
  - name: parent
    initial: true
    regions:
      - states:
          - name: child1
            initial: true
            final: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testHandlesAllSectionsTogether(): void
    {
        // Arrange - Complete configuration with all sections
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\Transitions\TransitionsFeature
  container:
    services:
      value:
        value: 42
states:
  - name: root
    initial: true
    regions:
      - states:
          - name: nested
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
        $this->assertEquals('root', $region->currentState());
    }
    
    public function testWorksWithMinimalConfiguration(): void
    {
        // Arrange - Minimal config (just states)
        $yaml = <<<YAML
states:
  - name: only
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
    }
}
