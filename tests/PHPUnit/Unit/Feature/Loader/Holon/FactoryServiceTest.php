<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: Container registers factory-based services
 */
#[Group('loader'), Group('holon'), Group('holon-container')]
class FactoryServiceTest extends TestCase
{
    public function testRegistersFactoryService(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $factory = fn() => new \stdClass();

        $containerConfig = [
            'services' => [
                'factory.service' => ['factory' => $factory]
            ]
        ];

        // Act
        $container = $method->invoke(null, $containerConfig);
        $instance = $container->get('factory.service');

        // Assert
        $this->assertInstanceOf(\stdClass::class, $instance);
    }

    public function testFactoryReceivesContainerAsParameter(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $receivedContainer = null;
        $factory = function ($container) use (&$receivedContainer) {
            $receivedContainer = $container;
            return new \stdClass();
        };

        $containerConfig = [
            'services' => [
                'factory.service' => ['factory' => $factory]
            ]
        ];

        // Act
        $container = $method->invoke(null, $containerConfig);
        $container->get('factory.service');

        // Assert
        $this->assertSame($container, $receivedContainer);
    }

    public function testFactoryCanAccessOtherServices(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $factory = function ($container) {
            $dependency = $container->get('dependency');
            $obj = new \stdClass();
            $obj->dependency = $dependency;
            return $obj;
        };

        $containerConfig = [
            'services' => [
                'dependency' => ['value' => 'dependency_value'],
                'factory.service' => ['factory' => $factory]
            ]
        ];

        // Act
        $container = $method->invoke(null, $containerConfig);
        $instance = $container->get('factory.service');

        // Assert
        $this->assertEquals('dependency_value', $instance->dependency);
    }

    public function testFactoryIsLazilyInvoked(): void
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

        // Assert - not invoked yet
        $this->assertEquals(0, $invocationCount);

        // Act - retrieve service
        $container->get('factory.service');

        // Assert - invoked once
        $this->assertEquals(1, $invocationCount);
    }
}
