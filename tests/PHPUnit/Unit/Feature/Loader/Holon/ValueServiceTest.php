<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: Container registers direct value services
 */
#[Group('loader'), Group('holon'), Group('holon-container')]
class ValueServiceTest extends TestCase
{
    public function testRegistersSimpleValueService(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        
        $expectedValue = 'simple_string';
        $containerConfig = [
            'services' => [
                'string.service' => ['value' => $expectedValue]
            ]
        ];
        
        // Act
        $container = $method->invoke(null, $containerConfig);
        $actualValue = $container->get('string.service');
        
        // Assert
        $this->assertEquals($expectedValue, $actualValue);
    }
    
    public function testRegistersArrayValueService(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        
        $expectedValue = ['key1' => 'value1', 'key2' => 'value2'];
        $containerConfig = [
            'services' => [
                'array.service' => ['value' => $expectedValue]
            ]
        ];
        
        // Act
        $container = $method->invoke(null, $containerConfig);
        $actualValue = $container->get('array.service');
        
        // Assert
        $this->assertEquals($expectedValue, $actualValue);
    }
    
    public function testRegistersObjectValueService(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        
        $expectedValue = new \stdClass();
        $expectedValue->property = 'test';
        
        $containerConfig = [
            'services' => [
                'object.service' => ['value' => $expectedValue]
            ]
        ];
        
        // Act
        $container = $method->invoke(null, $containerConfig);
        $actualValue = $container->get('object.service');
        
        // Assert
        $this->assertSame($expectedValue, $actualValue);
        $this->assertEquals('test', $actualValue->property);
    }
    
    public function testRegistersMultipleValueServices(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);
        
        $containerConfig = [
            'services' => [
                'service1' => ['value' => 'value1'],
                'service2' => ['value' => 'value2'],
                'service3' => ['value' => 'value3'],
            ]
        ];
        
        // Act
        $container = $method->invoke(null, $containerConfig);
        
        // Assert
        $this->assertEquals('value1', $container->get('service1'));
        $this->assertEquals('value2', $container->get('service2'));
        $this->assertEquals('value3', $container->get('service3'));
    }
}
