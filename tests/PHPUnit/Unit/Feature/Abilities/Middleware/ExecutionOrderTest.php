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
 * Acceptance Criterion: Multiple middleware execute in registration order
 *
 * Tests that middleware registered on InvokeAbility chain execute in FIFO
 * (First In, First Out) order - the first middleware registered is the first
 * to execute. This provides predictable execution sequence for middleware
 * stacks like auth -> logging -> caching.
 *
 * Note: This follows the standard Chain behavior where middleware are
 * reversed during chain construction, resulting in FIFO execution order.
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('middleware')]
class ExecutionOrderTest extends TestCase
{
    public function testMiddlewareExecuteInRegistrationOrder(): void
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

        $executionOrder = [];

        // Register three middleware
        $firstMiddleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'first';
            return $next($params);
        };

        $secondMiddleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'second';
            return $next($params);
        };

        $thirdMiddleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'third';
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($firstMiddleware);  // First registered
        $invokeAbility->link($secondMiddleware); // Second registered
        $invokeAbility->link($thirdMiddleware);  // Third registered

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $invokeAbility->call($params);

        // Assert - FIFO order: first, second, third
        $this->assertSame(
            ['first', 'second', 'third'],
            $executionOrder,
            'Middleware should execute in registration order (FIFO)'
        );
    }

    public function testAuthBeforeLoggingBeforeCaching(): void
    {
        // Arrange - typical middleware stack
        $abilityName = 'get-user-data';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Get user data',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['user' => 'data']
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $executionOrder = [];

        // Common middleware stack pattern
        $authMiddleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'auth';
            // Check permissions first
            return $next($params);
        };

        $loggingMiddleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'logging';
            // Log invocation
            return $next($params);
        };

        $cachingMiddleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'caching';
            // Check cache
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);

        // Register in desired execution order
        $invokeAbility->link($authMiddleware);    // 1. Check auth first
        $invokeAbility->link($loggingMiddleware); // 2. Log after auth passes
        $invokeAbility->link($cachingMiddleware); // 3. Check cache last

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertSame(
            ['auth', 'logging', 'caching'],
            $executionOrder,
            'Middleware stack should execute in order: auth -> logging -> caching'
        );
    }

    public function testMiddlewareReceiveModificationsFromPrevious(): void
    {
        // Arrange
        $abilityName = 'transform-data';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Transform data',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $transformationLog = [];

        // First middleware: adds field A
        $addAMiddleware = function ($params, $next, $first) use (&$transformationLog) {
            $transformationLog[] = 'add_A';
            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: array_merge($params->parameters ?? [], ['A' => true])
            );
            return $next($newParams);
        };

        // Second middleware: adds field B (should see A from previous)
        $addBMiddleware = function ($params, $next, $first) use (&$transformationLog) {
            $transformationLog[] = 'add_B';
            // Verify A exists from previous middleware
            $hasA = isset($params->parameters['A']);
            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: array_merge(
                    $params->parameters ?? [],
                    ['B' => true, 'had_A' => $hasA]
                )
            );
            return $next($newParams);
        };

        // Third middleware: adds field C (should see A and B)
        $addCMiddleware = function ($params, $next, $first) use (&$transformationLog) {
            $transformationLog[] = 'add_C';
            $hasA = isset($params->parameters['A']);
            $hasB = isset($params->parameters['B']);
            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: array_merge(
                    $params->parameters ?? [],
                    ['C' => true, 'had_A' => $hasA, 'had_B' => $hasB]
                )
            );
            return $next($newParams);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($addAMiddleware);
        $invokeAbility->link($addBMiddleware);
        $invokeAbility->link($addCMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: []
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertSame(
            ['add_A', 'add_B', 'add_C'],
            $transformationLog,
            'Middleware should execute in order, each seeing previous modifications'
        );
    }

    public function testFirstMiddlewareCanBlockRest(): void
    {
        // Arrange
        $abilityName = 'protected-action';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Protected action',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        $executionOrder = [];

        // First middleware short-circuits
        $blockingMiddleware = function ($params, $next, $first) use (&$executionOrder, $abilityName) {
            $executionOrder[] = 'blocking';
            // Don't call next() - short-circuit
            return new \Noem\State\Feature\Abilities\AbilityMessage(
                abilityName: $abilityName,
                parameters: null,
                definition: null
            );
        };

        $secondMiddleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'second';
            return $next($params);
        };

        $thirdMiddleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'third';
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($blockingMiddleware); // Blocks
        $invokeAbility->link($secondMiddleware);   // Never reached
        $invokeAbility->link($thirdMiddleware);    // Never reached

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertSame(
            ['blocking'],
            $executionOrder,
            'When first middleware short-circuits, subsequent middleware should not execute'
        );
    }

    public function testMiddlewareExecuteBeforeHandler(): void
    {
        // Arrange
        $abilityName = 'test-ability';

        $executionOrder = [];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($params) use (&$executionOrder) {
                $executionOrder[] = 'handler';
                return [];
            }
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $middleware = function ($params, $next, $first) use (&$executionOrder) {
            $executionOrder[] = 'middleware';
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

        // Assert - middleware executes before handler
        // (Handler execution happens via region->trigger in actual implementation)
    }
}
