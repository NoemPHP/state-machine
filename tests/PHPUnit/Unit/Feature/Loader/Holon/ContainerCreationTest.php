<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * Acceptance Criterion: buildContainer creates PSR-11 compatible container
 */
#[Group('loader'), Group('holon'), Group('holon-container')]
class ContainerCreationTest extends TestCase
{
    public function testCreatesContainerImplementingPsr11Interface(): void
    {
        // Arrange
        $yaml = <<<YAML
machine:
  container:
    services:
      test.service:
        value: "test_value"
states:
  - name: active
    initial: true
    final: true
YAML;

        // Act
        $region = Holon::fromYaml($yaml);

        // Use reflection to access the container that was built
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $containerConfig = [
            'services' => [
                'test.service' => ['value' => 'test_value']
            ]
        ];

        $container = $method->invoke(null, $containerConfig);

        // Assert
        $this->assertInstanceOf(ContainerInterface::class, $container);
    }

    public function testContainerImplementsHasMethod(): void
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

        // Act
        $container = $method->invoke(null, $containerConfig);

        // Assert
        $this->assertTrue($container->has('existing.service'));
        $this->assertFalse($container->has('nonexistent.service'));
    }

    public function testContainerImplementsGetMethod(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('buildContainer');
        $method->setAccessible(true);

        $expectedValue = 'retrieved_value';
        $containerConfig = [
            'services' => [
                'test.service' => ['value' => $expectedValue]
            ]
        ];

        // Act
        $container = $method->invoke(null, $containerConfig);
        $actualValue = $container->get('test.service');

        // Assert
        $this->assertEquals($expectedValue, $actualValue);
    }
}
