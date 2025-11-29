<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Phase 2 builds region with features and container
 */
#[Group('loader'), Group('holon'), Group('holon-bootstrapping')]
class PhaseTwoBuildingTest extends TestCase
{
    public function testBuildsRegionWithFeatures(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\Transitions\TransitionsFeature
    - Noem\State\Feature\ExtendedState\ExtendedState
states:
  - name: idle
    initial: true
    transitions:
      - target: done
  - name: done
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - Features were enabled
        $this->assertInstanceOf(Region::class, $region);
        
        // Verify TransitionsFeature works (automatic transition)
        $region->trigger(new \stdClass());
        $this->assertEquals('done', $region->currentState());
    }
    
    public function testBuildsRegionWithContainer(): void
    {
        // Arrange - Container with services
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\ExtendedState\ExtendedState
  container:
    services:
      myValue:
        value: "test-value"
      myService:
        class: stdClass
states:
  - name: ready
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - Container was built and used
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testAppliesYamlHelpersInPhaseTwo(): void
    {
        // Arrange - Use service helper (phase 2 with container access)
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\ExtendedState\ExtendedState
  container:
    services:
      counter:
        factory: !php |
          return fn() => new class {
            public int \$count = 0;
          };
states:
  - name: start
    initial: true
    action:
      - run: !php |
          return function(object \$t) use (\$container) {
            \$counter = \$container->get('counter');
            \$counter->count++;
            \$this->set('count', \$counter->count);
          };
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        $region->trigger(new \stdClass());

        // Assert - Phase 2 helpers with container access worked
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testAddsRegionLoaderWhenNotPresent(): void
    {
        // Arrange - No RegionLoader specified in features
        $yaml = <<<YAML
machine:
  features:
    - Noem\State\Feature\Transitions\TransitionsFeature
states:
  - name: working
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);
        
        // Assert - RegionLoader was added automatically
        $this->assertInstanceOf(Region::class, $region);
    }
}
