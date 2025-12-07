<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: Container caches resolved factory services
 */
#[Group('loader'), Group('holon'), Group('holon-container')]
class ServiceCachingTest extends TestCase
{
    public function testFactoryServiceIsResolvedOnlyOnce(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $invocationCount = 0;
        $factory = function () use (&$invocationCount) {
            $invocationCount++;
            return new \stdClass();
        };

        $containerConfig = [
            'services' => [
                'factory.service' => ['factory' => $factory]
            ]
        ];

        // Act
        $container = $method->invoke(null, $containerConfig);
        $instance1 = $container->get('factory.service');
        $instance2 = $container->get('factory.service');
        $instance3 = $container->get('factory.service');

        // Assert - factory invoked only once
        $this->assertEquals(1, $invocationCount);
        $this->assertSame($instance1, $instance2);
        $this->assertSame($instance2, $instance3);
    }

    public function testClassServiceIsCached(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $containerConfig = [
            'services' => [
                'class.service' => [
                    'class' => \stdClass::class
                ]
            ]
        ];

        // Act
        $container = $method->invoke(null, $containerConfig);
        $instance1 = $container->get('class.service');
        $instance2 = $container->get('class.service');

        // Assert - same instance returned (singleton behavior)
        $this->assertSame($instance1, $instance2);
    }

    public function testValueServiceAlwaysReturnsSameReference(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $originalValue = new \stdClass();
        $originalValue->id = 'original';

        $containerConfig = [
            'services' => [
                'value.service' => ['value' => $originalValue]
            ]
        ];

        // Act
        $container = $method->invoke(null, $containerConfig);
        $retrieved1 = $container->get('value.service');
        $retrieved2 = $container->get('value.service');

        // Assert - exact same reference
        $this->assertSame($originalValue, $retrieved1);
        $this->assertSame($retrieved1, $retrieved2);
    }

    public function testMultipleServicesAreCachedIndependently(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $invocationCounts = ['service1' => 0, 'service2' => 0];

        $factory1 = function () use (&$invocationCounts) {
            $invocationCounts['service1']++;
            $obj = new \stdClass();
            $obj->name = 'service1';
            return $obj;
        };

        $factory2 = function () use (&$invocationCounts) {
            $invocationCounts['service2']++;
            $obj = new \stdClass();
            $obj->name = 'service2';
            return $obj;
        };

        $containerConfig = [
            'services' => [
                'service1' => ['factory' => $factory1],
                'service2' => ['factory' => $factory2],
            ]
        ];

        // Act
        $container = $method->invoke(null, $containerConfig);
        $s1_1 = $container->get('service1');
        $s2_1 = $container->get('service2');
        $s1_2 = $container->get('service1');
        $s2_2 = $container->get('service2');

        // Assert - each factory invoked only once
        $this->assertEquals(1, $invocationCounts['service1']);
        $this->assertEquals(1, $invocationCounts['service2']);

        // Assert - instances are cached independently
        $this->assertSame($s1_1, $s1_2);
        $this->assertSame($s2_1, $s2_2);
        $this->assertNotSame($s1_1, $s2_1);
    }
}
