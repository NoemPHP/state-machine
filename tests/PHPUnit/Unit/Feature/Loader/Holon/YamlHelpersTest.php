<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionClass;

/**
 * Acceptance Criterion: getYamlHelpers adds container access helpers
 */
#[Group('loader'), Group('holon'), Group('holon-helpers')]
class YamlHelpersTest extends TestCase
{
    public function testIncludesBootstrapHelpers(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getYamlHelpers');
        $method->setAccessible(true);

        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);

        // Act
        $helpers = $method->invoke(null, $container);

        // Assert - should include all bootstrap helpers
        $this->assertArrayHasKey('php', $helpers);
        $this->assertArrayHasKey('env', $helpers);
        $this->assertArrayHasKey('constant', $helpers);

        $this->assertInstanceOf(PhpEvalHelper::class, $helpers['php']);
    }

    public function testAddsGetHelperForContainerAccess(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getYamlHelpers');
        $method->setAccessible(true);

        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, [
            'services' => [
                'test.service' => ['value' => 'test_value']
            ]
        ]);

        // Act
        $helpers = $method->invoke(null, $container);

        // Assert
        $this->assertArrayHasKey('get', $helpers);
        $this->assertIsCallable($helpers['get']);

        // Verify get helper accesses container
        $result = $helpers['get']('test.service');
        $this->assertEquals('test_value', $result);
    }

    public function testAddsServiceHelperForContainerAccess(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getYamlHelpers');
        $method->setAccessible(true);

        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, [
            'services' => [
                'my.service' => ['value' => 'service_value']
            ]
        ]);

        // Act
        $helpers = $method->invoke(null, $container);

        // Assert
        $this->assertArrayHasKey('service', $helpers);
        $this->assertIsCallable($helpers['service']);

        // Verify service helper accesses container
        $result = $helpers['service']('my.service');
        $this->assertEquals('service_value', $result);
    }

    public function testProvidesAllFiveHelpers(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getYamlHelpers');
        $method->setAccessible(true);

        $buildContainer = $reflection->getMethod('buildContainer');
        $buildContainer->setAccessible(true);
        $container = $buildContainer->invoke(null, ['services' => []]);

        // Act
        $helpers = $method->invoke(null, $container);

        // Assert
        $this->assertIsArray($helpers);
        $this->assertArrayHasKey('php', $helpers);
        $this->assertArrayHasKey('get', $helpers);
        $this->assertArrayHasKey('env', $helpers);
        $this->assertArrayHasKey('constant', $helpers);
        $this->assertArrayHasKey('service', $helpers);
        $this->assertCount(5, $helpers);
    }
}
