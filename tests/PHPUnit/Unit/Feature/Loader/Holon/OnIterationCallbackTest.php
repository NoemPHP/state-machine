<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: runEventLoop invokes onIteration callback when provided
 */
#[Group('loader'), Group('holon'), Group('holon-event-loop')]
class OnIterationCallbackTest extends TestCase
{
    public function testInvokesOnIterationCallback(): void
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
        $callbackInvoked = false;
        
        $onIteration = function() use (&$callbackInvoked) {
            $callbackInvoked = true;
        };
        
        // Use reflection
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $config = [
            'onIteration' => $onIteration,
            'maxIterations' => 100
        ];
        
        // Act
        $method->invoke(null, $region, $config, $container);
        
        // Assert
        $this->assertTrue($callbackInvoked);
    }
    
    public function testCallbackReceivesRegion(): void
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
        
        $onIteration = function(Region $r) use (&$receivedRegion) {
            $receivedRegion = $r;
        };
        
        // Use reflection
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $config = [
            'onIteration' => $onIteration,
            'maxIterations' => 100
        ];
        
        // Act
        $method->invoke(null, $region, $config, $container);
        
        // Assert
        $this->assertSame($region, $receivedRegion);
    }
    
    public function testCallbackReceivesTrigger(): void
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
        $receivedTrigger = null;
        
        $onIteration = function(Region $r, $trigger) use (&$receivedTrigger) {
            $receivedTrigger = $trigger;
        };
        
        // Use reflection
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $config = [
            'onIteration' => $onIteration,
            'maxIterations' => 100
        ];
        
        // Act
        $method->invoke(null, $region, $config, $container);
        
        // Assert
        $this->assertNotNull($receivedTrigger);
    }
    
    public function testCallbackReceivesIterationNumber(): void
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
        $receivedIterations = [];
        
        $onIteration = function(Region $r, $trigger, int $iteration) use (&$receivedIterations) {
            $receivedIterations[] = $iteration;
        };
        
        // Use reflection
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $config = [
            'onIteration' => $onIteration,
            'maxIterations' => 100
        ];
        
        // Act
        $method->invoke(null, $region, $config, $container);
        
        // Assert
        $this->assertContains(0, $receivedIterations);
        $this->assertGreaterThan(0, count($receivedIterations));
    }
    
    public function testDoesNotFailWhenCallbackNotProvided(): void
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
        
        // Use reflection
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('runEventLoop');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        // No onIteration callback
        $config = ['maxIterations' => 100];
        
        // Act
        $result = $method->invoke(null, $region, $config, $container);
        
        // Assert - should complete without error
        $this->assertNotNull($result);
    }
}
