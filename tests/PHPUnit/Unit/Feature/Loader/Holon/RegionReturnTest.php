<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon returns Region instance by default
 */
#[Group('loader'), Group('holon'), Group('holon-bootstrapping')]
class RegionReturnTest extends TestCase
{
    public function testReturnsRegionByDefault(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: initial
    initial: true
  - name: final
    final: true
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $result);
    }
    
    public function testReturnsRegionWhenAutoRunIsFalse(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  eventLoop:
    autoRun: false
states:
  - name: start
    initial: true
    final: true
YAML;

        // Act
        $result = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $result);
    }
    
    public function testReturnsWorkingRegion(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: a
    initial: true
    transitions:
      - target: b
  - name: b
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - Verify region is functional
        $this->assertEquals('a', $region->currentState());
        $region->trigger(new \stdClass());
        $this->assertEquals('b', $region->currentState());
        $this->assertTrue($region->isFinal());
    }
}
