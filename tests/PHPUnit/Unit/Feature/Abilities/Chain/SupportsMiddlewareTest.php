<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Chain;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: InvokeAbility supports middleware via link() method
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class SupportsMiddlewareTest extends TestCase
{
    public function testHasLinkMethod(): void
    {
        // Arrange
        $registry = $this->createMock(AbilityRegistry::class);
        $invokeAbility = new InvokeAbility($registry);

        // Assert
        $this->assertTrue(
            method_exists($invokeAbility, 'link'),
            'InvokeAbility must have link() method for middleware registration'
        );
    }

    public function testLinkMethodAcceptsCallable(): void
    {
        // Arrange
        $registry = $this->createMock(AbilityRegistry::class);
        $invokeAbility = new InvokeAbility($registry);

        $middleware = function ($params, $next, $first) {
            return $next($params);
        };

        // Act - should not throw
        $deregister = $invokeAbility->link($middleware);

        // Assert
        $this->assertIsCallable($deregister);
    }

    public function testMiddlewareReceivesParams(): void
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

        $receivedParams = null;
        $middleware = function ($params, $next, $first) use (&$receivedParams) {
            $receivedParams = $params;
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($middleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['test' => 'data']
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertInstanceOf(InvokeAbilityParams::class, $receivedParams);
        $this->assertSame($params, $receivedParams);
    }

    public function testMiddlewareReceivesNextCallable(): void
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

        $receivedNext = null;
        $middleware = function ($params, $next, $first) use (&$receivedNext) {
            $receivedNext = $next;
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
        $this->assertIsCallable($receivedNext);
    }

    public function testMiddlewareCanInterceptInvocation(): void
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
        $this->assertTrue($middlewareCalled, 'Middleware should be called during invocation');
    }
}
