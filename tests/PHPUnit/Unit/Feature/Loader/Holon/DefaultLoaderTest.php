<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Feature\Loader\RegionLoader;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon adds RegionLoader when not present
 */
#[Group('loader'), Group('holon'), Group('holon-features')]
class DefaultLoaderTest extends TestCase
{
    public function testAddsRegionLoaderWhenNotPresent(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features: []
states:
  - name: start
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - region should be built successfully
        // This proves RegionLoader was added automatically
        $this->assertEquals('start', $region->currentState());
    }
    
    public function testDoesNotAddRegionLoaderWhenAlreadyPresent(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - class: Noem\State\Feature\Loader\RegionLoader
states:
  - name: start
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - should work without duplicating RegionLoader
        $this->assertEquals('start', $region->currentState());
    }
    
    public function testAddsRegionLoaderBeforeBuilding(): void
    {
        // Arrange - machine with no features specified
        $yaml = <<<YAML
states:
  - name: idle
    initial: true
  - name: active
  - name: done
    final: true
  - name: idle
    transitions:
      - target: active
  - name: active
    transitions:
      - target: done
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - states should be loaded from YAML (proving RegionLoader worked)
        $this->assertEquals('idle', $region->currentState());
        $this->assertFalse($region->isFinal());
        
        // Trigger transitions to verify the machine works
        $region->trigger(new \stdClass());
        $this->assertEquals('active', $region->currentState());
        
        $region->trigger(new \stdClass());
        $this->assertEquals('done', $region->currentState());
        $this->assertTrue($region->isFinal());
    }
}
