<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Acceptance Criterion: instantiateFeatures throws for non-existent classes
 */
#[Group('loader'), Group('holon'), Group('holon-features')]
class MissingClassErrorTest extends TestCase
{
    public function testThrowsExceptionForNonExistentClass(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $nonExistentClass = 'Noem\\State\\Feature\\NonExistentFeature';
        $featuresConfig = [
            ['class' => $nonExistentClass]
        ];
        
        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Feature class '$nonExistentClass' does not exist");
        
        // Act
        $method->invoke(null, $featuresConfig, $container);
    }
    
    public function testThrowsExceptionWhenClassKeyMissing(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        // Config without 'class' key
        $featuresConfig = [
            ['config' => ['some' => 'config']]
        ];
        
        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Feature configuration missing 'class' key");
        
        // Act
        $method->invoke(null, $featuresConfig, $container);
    }
    
    public function testThrowsExceptionForEmptyClassName(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $featuresConfig = [
            ['class' => '']
        ];
        
        // Assert
        $this->expectException(RuntimeException::class);
        
        // Act
        $method->invoke(null, $featuresConfig, $container);
    }
    
    public function testExceptionIncludesClassName(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $nonExistentClass = 'My\\Custom\\MissingFeature';
        $featuresConfig = [
            ['class' => $nonExistentClass]
        ];
        
        // Act & Assert
        try {
            $method->invoke(null, $featuresConfig, $container);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString($nonExistentClass, $e->getMessage());
        }
    }
}
