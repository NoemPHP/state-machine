<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Acceptance Criterion: Container throws RuntimeException for missing services
 */
#[Group('loader'), Group('holon'), Group('holon-container')]
class MissingServiceErrorTest extends TestCase
{
    public function testThrowsRuntimeExceptionForMissingService(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        
        $containerConfig = [
            'services' => [
                'existing.service' => ['value' => 'exists']
            ]
        ];
        
        $container = $method->invoke(null, $containerConfig);
        
        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Service 'nonexistent.service' not found in container");
        
        // Act
        $container->get('nonexistent.service');
    }
    
    public function testHasReturnsFalseForMissingService(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        
        $containerConfig = [
            'services' => [
                'existing.service' => ['value' => 'exists']
            ]
        ];
        
        $container = $method->invoke(null, $containerConfig);
        
        // Act
        $result = $container->has('nonexistent.service');
        
        // Assert
        $this->assertFalse($result);
    }
    
    public function testThrowsExceptionEvenIfServiceDefinedButFactoryIsNotCallable(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        
        // Manually create a broken configuration (factory that's not callable)
        $containerConfig = [
            'services' => [
                'broken.service' => ['factory' => 'not_a_callable']
            ]
        ];
        
        $container = $method->invoke(null, $containerConfig);
        
        // Assert
        $this->expectException(RuntimeException::class);
        
        // Act - should fail because factory is not callable
        $container->get('broken.service');
    }
    
    public function testExceptionMessageIncludesServiceId(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        
        $container = $method->invoke(null, ['services' => []]);
        
        // Assert
        try {
            $container->get('my.custom.service');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('my.custom.service', $e->getMessage());
        }
    }
}
