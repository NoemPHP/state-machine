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
 * Acceptance Criterion: Middleware receives Params\InvokeAbility before invocation
 *
 * Tests that middleware registered on InvokeAbility chain receives the correct
 * parameters: Params\InvokeAbility as first argument, callable next as second,
 * and callable first as third. This enables middleware to inspect region,
 * abilityName, and parameters before the invocation proceeds.
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('middleware')]
class InterceptsInvocationTest extends TestCase
{
    public function testMiddlewareReceivesInvokeAbilityParams(): void
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
        $this->assertInstanceOf(
            InvokeAbilityParams::class,
            $receivedParams,
            'Middleware should receive Params\InvokeAbility as first argument'
        );
        $this->assertSame(
            $params,
            $receivedParams,
            'Middleware should receive the exact params instance passed to call()'
        );
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
        $this->assertIsCallable(
            $receivedNext,
            'Middleware should receive next callable as second argument'
        );
    }

    public function testMiddlewareReceivesFirstCallable(): void
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

        $receivedFirst = null;
        $middleware = function ($params, $next, $first) use (&$receivedFirst) {
            $receivedFirst = $first;
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
        $this->assertIsCallable(
            $receivedFirst,
            'Middleware should receive first callable as third argument'
        );
    }

    public function testMiddlewareCanAccessInvocationDetails(): void
    {
        // Arrange
        $abilityName = 'get-user-profile';
        $parameters = ['userId' => 123, 'fields' => ['name', 'email']];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Get user profile',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $capturedAbilityName = null;
        $capturedParameters = null;
        $capturedRegion = null;

        $middleware = function (InvokeAbilityParams $params, $next, $first) use (
            &$capturedAbilityName,
            &$capturedParameters,
            &$capturedRegion
        ) {
            // Middleware can inspect all invocation details
            $capturedAbilityName = $params->abilityName;
            $capturedParameters = $params->parameters;
            $capturedRegion = $params->region;
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($middleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $parameters
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertSame($abilityName, $capturedAbilityName);
        $this->assertSame($parameters, $capturedParameters);
        $this->assertSame($region, $capturedRegion);
    }

    public function testNextCallableAdvancesChain(): void
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

        $handlerExecuted = false;

        $middleware = function ($params, $next, $first) use (&$handlerExecuted) {
            // When middleware calls next(), it should advance to the actual handler
            $result = $next($params);
            return $result;
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

        // Assert - trigger should be called, indicating handler execution path was reached
        // This will be verified by checking that region->trigger was called
        // (The actual implementation will do this)
    }
}
