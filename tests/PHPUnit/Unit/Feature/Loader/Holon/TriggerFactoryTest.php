<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: runEventLoop calls trigger factory for each iteration
 */
#[Group('loader'), Group('holon'), Group('holon-event-loop')]
class TriggerFactoryTest extends TestCase
{
    public function testCallsTriggerFactoryForEachIteration(): void
    {
        // Arrange
        $yaml = <<<YAML
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

        $region = Holon::fromYaml($yaml);
        $callCount = 0;
        
        $triggerFactory = function(int $iteration, Region $r) use (&$callCount) {
            $callCount++;
            return new \stdClass();
        };
        
        // Use reflection to call runEventLoop directly
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $config = ['trigger' => $triggerFactory, 'maxIterations' => 100];
        
        // Act
        $method->invoke(null, $region, $config, $container);
        
        // Assert - should have been called for each iteration until final state
        $this->assertGreaterThan(0, $callCount);
    }
    
    public function testTriggerFactoryReceivesIterationNumber(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: start
    initial: true
  - name: end
    final: true
  - name: start
    transitions:
      - target: end
YAML;

        $region = Holon::fromYaml($yaml);
        $receivedIterations = [];
        
        $triggerFactory = function(int $iteration) use (&$receivedIterations) {
            $receivedIterations[] = $iteration;
            return new \stdClass();
        };
        
        // Use reflection
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $config = ['trigger' => $triggerFactory, 'maxIterations' => 100];
        
        // Act
        $method->invoke(null, $region, $config, $container);
        
        // Assert
        $this->assertContains(0, $receivedIterations); // First iteration is 0
    }
    
    public function testTriggerFactoryReceivesRegionReference(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: start
    initial: true
  - name: end
    final: true
  - name: start
    transitions:
      - target: end
YAML;

        $region = Holon::fromYaml($yaml);
        $receivedRegion = null;
        
        $triggerFactory = function(int $iteration, Region $r) use (&$receivedRegion) {
            $receivedRegion = $r;
            return new \stdClass();
        };
        
        // Use reflection
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $config = ['trigger' => $triggerFactory, 'maxIterations' => 100];
        
        // Act
        $method->invoke(null, $region, $config, $container);
        
        // Assert
        $this->assertSame($region, $receivedRegion);
    }
    
    public function testTriggerFactoryReceivesContainer(): void
    {
        // Arrange
        $yaml = <<<YAML
states:
  - name: start
    initial: true
  - name: end
    final: true
  - name: start
    transitions:
      - target: end
YAML;

        $region = Holon::fromYaml($yaml);
        $receivedContainer = null;
        
        $triggerFactory = function(int $iteration, Region $r, $container) use (&$receivedContainer) {
            $receivedContainer = $container;
            return new \stdClass();
        };
        
        // Use reflection
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $config = ['trigger' => $triggerFactory, 'maxIterations' => 100];
        
        // Act
        $method->invoke(null, $region, $config, $container);
        
        // Assert
        $this->assertSame($container, $receivedContainer);
    }
}
