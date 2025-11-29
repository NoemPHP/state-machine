<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Helper\PhpEvalHelper;
use Noem\State\Feature\Loader\Holon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: getBootstrapHelpers provides php, env, constant helpers
 */
#[Group('loader'), Group('holon'), Group('holon-helpers')]
class BootstrapHelpersTest extends TestCase
{
    public function testProvidesPhpHelper(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getBootstrapHelpers');
        $method->setAccessible(true);
        
        // Act
        $helpers = $method->invoke(null);
        
        // Assert
        $this->assertArrayHasKey('php', $helpers);
        $this->assertInstanceOf(PhpEvalHelper::class, $helpers['php']);
    }
    
    public function testProvidesEnvHelper(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getBootstrapHelpers');
        $method->setAccessible(true);
        
        // Set an environment variable for testing
        $_ENV['TEST_HOLON_VAR'] = 'test_value';
        
        // Act
        $helpers = $method->invoke(null);
        
        // Assert
        $this->assertArrayHasKey('env', $helpers);
        $this->assertIsCallable($helpers['env']);
        
        // Test the env helper works
        $result = $helpers['env']('TEST_HOLON_VAR');
        $this->assertEquals('test_value', $result);
        
        // Cleanup
        unset($_ENV['TEST_HOLON_VAR']);
    }
    
    public function testEnvHelperReturnsNullForMissingVariable(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getBootstrapHelpers');
        $method->setAccessible(true);
        
        // Act
        $helpers = $method->invoke(null);
        $result = $helpers['env']('NONEXISTENT_VAR_' . uniqid());
        
        // Assert
        $this->assertNull($result);
    }
    
    public function testProvidesConstantHelper(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getBootstrapHelpers');
        $method->setAccessible(true);
        
        // Act
        $helpers = $method->invoke(null);
        
        // Assert
        $this->assertArrayHasKey('constant', $helpers);
        $this->assertIsCallable($helpers['constant']);
        
        // Test with a known constant
        $result = $helpers['constant']('PHP_VERSION');
        $this->assertEquals(PHP_VERSION, $result);
    }
    
    public function testProvidesAllThreeHelpers(): void
    {
        // Arrange
        $reflection = new ReflectionClass(Holon::class);
        $method = $reflection->getMethod('getBootstrapHelpers');
        $method->setAccessible(true);
        
        // Act
        $helpers = $method->invoke(null);
        
        // Assert
        $this->assertIsArray($helpers);
        $this->assertArrayHasKey('php', $helpers);
        $this->assertArrayHasKey('env', $helpers);
        $this->assertArrayHasKey('constant', $helpers);
        $this->assertCount(3, $helpers);
    }
}
