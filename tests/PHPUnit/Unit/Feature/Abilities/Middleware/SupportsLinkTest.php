<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Middleware;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: InvokeAbility.link() registers middleware interceptors
 *
 * Tests that InvokeAbility supports middleware registration through the link() method,
 * which is inherited from the Chain base class. Middleware can intercept ability
 * invocations for logging, authorization, caching, and other cross-cutting concerns.
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('middleware')]
class SupportsLinkTest extends TestCase
{
    public function testInvokeAbilityHasLinkMethod(): void
    {
        // Arrange
        $registry = $this->createMock(AbilityRegistry::class);
        $invokeAbility = new InvokeAbility($registry);

        // Assert - InvokeAbility inherits link() from Chain
        $this->assertTrue(
            method_exists($invokeAbility, 'link'),
            'InvokeAbility must have link() method for middleware registration'
        );
    }

    public function testLinkMethodAcceptsMiddlewareCallable(): void
    {
        // Arrange
        $registry = $this->createMock(AbilityRegistry::class);
        $invokeAbility = new InvokeAbility($registry);

        $middleware = function ($params, $next, $first) {
            return $next($params);
        };

        // Act - link() should accept middleware and return deregister function
        $deregister = $invokeAbility->link($middleware);

        // Assert
        $this->assertIsCallable(
            $deregister,
            'link() should return a deregister callable'
        );
    }

    public function testMiddlewareIsInvokedDuringChainExecution(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['result' => 'success']
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $middlewareCalled = false;
        $middleware = function ($params, $next, $first) use (&$middlewareCalled) {
            $middlewareCalled = true;
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($middleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertTrue(
            $middlewareCalled,
            'Middleware should be called during chain execution'
        );
    }

    public function testMultipleMiddlewareCanBeRegistered(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $firstMiddlewareCalled = false;
        $secondMiddlewareCalled = false;

        $firstMiddleware = function ($params, $next, $first) use (&$firstMiddlewareCalled) {
            $firstMiddlewareCalled = true;
            return $next($params);
        };

        $secondMiddleware = function ($params, $next, $first) use (&$secondMiddlewareCalled) {
            $secondMiddlewareCalled = true;
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($firstMiddleware);
        $invokeAbility->link($secondMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertTrue($firstMiddlewareCalled, 'First middleware should be called');
        $this->assertTrue($secondMiddlewareCalled, 'Second middleware should be called');
    }

    public function testDeregisterFunctionRemovesMiddleware(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $middlewareCallCount = 0;
        $middleware = function ($params, $next, $first) use (&$middlewareCallCount) {
            $middlewareCallCount++;
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $deregister = $invokeAbility->link($middleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act - call once with middleware
        $invokeAbility->call($params);

        // Deregister middleware
        $deregister();

        // Call again after deregistration
        $invokeAbility->call($params);

        // Assert - middleware should only be called once (before deregistration)
        $this->assertSame(
            1,
            $middlewareCallCount,
            'Middleware should not be called after deregistration'
        );
    }
}
