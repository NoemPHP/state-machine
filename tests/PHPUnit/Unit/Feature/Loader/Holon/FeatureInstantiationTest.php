<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\Holon;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Acceptance Criterion: instantiateFeatures creates feature instances from class names
 */
#[Group('loader'), Group('holon'), Group('holon-features')]
class FeatureInstantiationTest extends TestCase
{
    public function testInstantiatesFeatureFromClassName(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $featuresConfig = [
            ['class' => RegionLoader::class]
        ];
        
        // Act
        $features = $method->invoke(null, $featuresConfig, $container);
        
        // Assert
        $this->assertCount(1, $features);
        $this->assertInstanceOf(RegionLoader::class, $features[0]);
    }
    
    public function testInstantiatesFeatureFromStringClassName(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        // Simple string format (not array)
        $featuresConfig = [
            RegionLoader::class
        ];
        
        // Act
        $features = $method->invoke(null, $featuresConfig, $container);
        
        // Assert
        $this->assertCount(1, $features);
        $this->assertInstanceOf(RegionLoader::class, $features[0]);
    }
    
    public function testInstantiatesMultipleFeatures(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        // Create a test feature for this test
        $testFeature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };
        $testFeatureClass = get_class($testFeature);
        
        $featuresConfig = [
            RegionLoader::class,
            ['class' => $testFeatureClass]
        ];
        
        // Act
        $features = $method->invoke(null, $featuresConfig, $container);
        
        // Assert
        $this->assertCount(2, $features);
        $this->assertInstanceOf(RegionLoader::class, $features[0]);
        $this->assertInstanceOf($testFeatureClass, $features[1]);
    }
    
    public function testReturnsEmptyArrayForEmptyConfig(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $featuresConfig = [];
        
        // Act
        $features = $method->invoke(null, $featuresConfig, $container);
        
        // Assert
        $this->assertIsArray($features);
        $this->assertEmpty($features);
    }
}
