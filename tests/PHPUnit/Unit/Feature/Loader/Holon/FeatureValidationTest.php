<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * Acceptance Criterion: instantiateFeatures validates Feature interface implementation
 */
#[Group('loader'), Group('holon'), Group('holon-features')]
class FeatureValidationTest extends TestCase
{
    public function testThrowsExceptionForNonFeatureClass(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);

        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);

        // Use a class that does NOT implement Feature
        $featuresConfig = [
            ['class' => \stdClass::class]
        ];

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Class 'stdClass' must implement Feature interface");

        // Act
        $method->invoke(null, $featuresConfig, $container);
    }

    public function testAcceptsValidFeatureImplementation(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);

        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);

        // Use RegionLoader which implements Feature
        $featuresConfig = [
            ['class' => \Noem\State\Feature\Loader\RegionLoader::class]
        ];

        // Act
        $features = $method->invoke(null, $featuresConfig, $container);

        // Assert - no exception thrown
        $this->assertCount(1, $features);
    }

    public function testValidatesAllFeaturesInConfig(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('instantiateFeatures');
        $method->setAccessible(true);

        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);

        // First is valid, second is invalid
        $featuresConfig = [
            ['class' => \Noem\State\Feature\Loader\RegionLoader::class],
            ['class' => \stdClass::class], // Invalid
        ];

        // Assert - should fail on second feature
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Class 'stdClass' must implement Feature interface");

        // Act
        $method->invoke(null, $featuresConfig, $container);
    }
}
