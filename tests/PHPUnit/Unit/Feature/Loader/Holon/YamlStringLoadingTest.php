<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Holon loads machine from YAML string
 */
#[Group('loader'), Group('holon'), Group('holon-bootstrapping')]
class YamlStringLoadingTest extends TestCase
{
    public function testLoadsFromInlineYamlString(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: start
    initial: true
    transitions:
      - target: end
  - name: end
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('start', $region->currentState());
        $this->assertFalse($region->isFinal());
        
        // Verify the machine actually works
        $region->trigger(new \stdClass());
        $this->assertEquals('end', $region->currentState());
        $this->assertTrue($region->isFinal());
    }
    
    public function testHandlesMultilineYamlString(): void
    {
        // Arrange - YAML with newlines (detecting inline vs file)
        $yaml = "states:\n  - name: active\n    initial: true\n    final: true";
        
        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('active', $region->currentState());
    }
}
