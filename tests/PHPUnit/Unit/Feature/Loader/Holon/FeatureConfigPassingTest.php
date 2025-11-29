<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Feature;
use Noem\State\Feature\Loader\Holon;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: instantiateFeatures passes config to feature constructor
 */
#[Group('loader'), Group('holon'), Group('holon-features')]
class FeatureConfigPassingTest extends TestCase
{
    public function testPassesConfigToFeatureConstructor(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        // Create a feature that accepts config in constructor
        $testFeature = new class(['option' => 'value']) implements Feature {
            public function __construct(public readonly array $config = []) {}
            public function __invoke(ChainMail $chainMail): void {}
        };
        $testFeatureClass = get_class($testFeature);
        
        $expectedConfig = ['option1' => 'value1', 'option2' => 'value2'];
        $featuresConfig = [
            [
                'class' => $testFeatureClass,
                'config' => $expectedConfig
            ]
        ];
        
        // Act
        $features = $method->invoke(null, $featuresConfig, $container);
        
        // Assert
        $this->assertCount(1, $features);
        $this->assertEquals($expectedConfig, $features[0]->config);
    }
    
    public function testInstantiatesWithoutConfigWhenNotProvided(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        // Feature with optional config parameter
        $testFeature = new class implements Feature {
            public function __construct(public readonly array $config = []) {}
            public function __invoke(ChainMail $chainMail): void {}
        };
        $testFeatureClass = get_class($testFeature);
        
        $featuresConfig = [
            ['class' => $testFeatureClass]
        ];
        
        // Act
        $features = $method->invoke(null, $featuresConfig, $container);
        
        // Assert
        $this->assertCount(1, $features);
        $this->assertEmpty($features[0]->config);
    }
    
    public function testSupportsEmptyConfigArray(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);
        
        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);
        
        $testFeature = new class implements Feature {
            public function __construct(public readonly array $config = []) {}
            public function __invoke(ChainMail $chainMail): void {}
        };
        $testFeatureClass = get_class($testFeature);
        
        $featuresConfig = [
            [
                'class' => $testFeatureClass,
                'config' => []
            ]
        ];
        
        // Act
        $features = $method->invoke(null, $featuresConfig, $container);
        
        // Assert
        $this->assertCount(1, $features);
        $this->assertEmpty($features[0]->config);
    }
}
