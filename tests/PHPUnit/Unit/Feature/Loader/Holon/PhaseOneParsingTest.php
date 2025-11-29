<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Phase 1 parses YAML and extracts machine configuration
 */
#[Group('loader'), Group('holon'), Group('holon-bootstrapping')]
class PhaseOneParsingTest extends TestCase
{
    public function testParsesYamlSuccessfully(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\Transitions\TransitionsFeature
  container:
    services:
      testValue:
        value: 42
states:
  - name: start
    initial: true
    final: true
YAML;

        // Act - Phase 1 happens internally, but we verify by successful Region creation
        $region = Holon::fromYaml($yaml);
        
        // Assert - If phase 1 parsing failed, we'd get an exception
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testExtractsMachineConfiguration(): void
    {
        // Arrange - YAML with machine section
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\Transitions\TransitionsFeature
  container:
    services:
      config:
        value: "configured"
states:
  - name: ready
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - Region was built with features from machine config
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('ready', $region->currentState());
    }
    
    public function testHandlesYamlWithBootstrapHelpers(): void
    {
        // Arrange - YAML using php helper (bootstrap phase)
        $yaml = <<<YAML
machine:
  container:
    services:
      computed:
        value: !php "return 2 + 2;"
states:
  - name: computed
    initial: true
    final: true
YAML;

        // Act - Bootstrap helpers should process !php tag
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
    }
}
