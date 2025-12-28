<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Middleware;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Middleware can short-circuit invocation by returning without next()
 *
 * Tests that middleware can prevent ability execution by returning early without
 * calling next(). This is essential for authorization middleware, caching middleware,
 * and other scenarios where the invocation should be blocked or satisfied without
 * executing the actual ability handler.
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('middleware')]
class ShortCircuitsTest extends TestCase
{
    public function testMiddlewareCanShortCircuitWithoutCallingNext(): void
    {
        // Arrange
        $abilityName = 'protected-operation';

        $handlerCalled = false;

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Protected operation',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($params) use (&$handlerCalled) {
                $handlerCalled = true;
                return ['executed' => true];
            }
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // Middleware that short-circuits without calling next()
        $authMiddleware = function (InvokeAbilityParams $params, $next, $first) use ($region, $abilityName) {
            // Don't call next() - short-circuit and return early
            // Return a mock AbilityMessage to satisfy the return type
            return AbilityMessage::create(
                abilityName: $abilityName,
                parameters: ['error' => 'unauthorized'],
                definition: null
            );
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($authMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $result = $invokeAbility->call($params);

        // Assert - handler should NOT be called
        $this->assertFalse(
            $handlerCalled,
            'Handler should not be called when middleware short-circuits'
        );
    }

    public function testShortCircuitPreventsSubsequentMiddleware(): void
    {
        // Arrange
        $abilityName = 'test-ability';

        $firstMiddlewareCalled = false;
        $secondMiddlewareCalled = false;
        $handlerCalled = false;

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($params) use (&$handlerCalled) {
                $handlerCalled = true;
                return [];
            }
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // First middleware: short-circuits
        $shortCircuitMiddleware = function ($params, $next, $first) use (&$firstMiddlewareCalled, $abilityName) {
            $firstMiddlewareCalled = true;
            // Return without calling next()
            return AbilityMessage::create(
                abilityName: $abilityName,
                parameters: null,
                definition: null
            );
        };

        // Second middleware: should never be reached
        $unreachedMiddleware = function ($params, $next, $first) use (&$secondMiddlewareCalled) {
            $secondMiddlewareCalled = true;
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($shortCircuitMiddleware);
        $invokeAbility->link($unreachedMiddleware); // Registered after, executes after in FIFO

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertTrue($firstMiddlewareCalled, 'First middleware should be called');
        $this->assertFalse($secondMiddlewareCalled, 'Second middleware should NOT be called after short-circuit');
        $this->assertFalse($handlerCalled, 'Handler should NOT be called after short-circuit');
    }

    public function testShortCircuitCanReturnCustomResponse(): void
    {
        // Arrange
        $abilityName = 'cached-query';
        $cachedData = ['cached' => true, 'value' => 42];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Cached query',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['cached' => false, 'value' => 100]
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // Caching middleware that short-circuits with cached response
        $cachingMiddleware = function ($params, $next, $first) use ($abilityName, $cachedData) {
            // Return cached response without calling next()
            return AbilityMessage::create(
                abilityName: $abilityName,
                parameters: $cachedData,
                definition: null
            );
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($cachingMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act
        $result = $invokeAbility->call($params);

        // Assert - result should be the cached response from middleware
        $this->assertInstanceOf(AbilityMessage::class, $result);
        $this->assertSame($cachedData, $result->parameters);
    }

    public function testAuthorizationMiddlewareCanBlockUnauthorizedAccess(): void
    {
        // Arrange
        $abilityName = 'delete-user';

        $handlerCalled = false;

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Delete user',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($params) use (&$handlerCalled) {
                $handlerCalled = true;
                return ['deleted' => true];
            }
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        $userPermissions = ['read', 'write']; // No 'delete' permission

        // Authorization middleware
        $authMiddleware = function ($params, $next, $first) use ($abilityName, $userPermissions) {
            $requiredPermission = 'delete';

            if (!in_array($requiredPermission, $userPermissions)) {
                // Unauthorized - short-circuit with error response
                return AbilityMessage::create(
                    abilityName: $abilityName,
                    parameters: ['error' => 'Permission denied', 'required' => $requiredPermission],
                    definition: null
                );
            }

            // Authorized - continue
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($authMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['userId' => 123]
        );

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertFalse($handlerCalled, 'Handler should not be called for unauthorized access');
        $this->assertInstanceOf(AbilityMessage::class, $result);
        $this->assertArrayHasKey('error', $result->parameters);
        $this->assertSame('Permission denied', $result->parameters['error']);
    }

    public function testShortCircuitPreventsRegionTrigger(): void
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

        // region->trigger should NOT be called when short-circuited
        $region->expects($this->never())->method('trigger');

        // Middleware that short-circuits
        $middleware = function ($params, $next, $first) use ($abilityName) {
            return AbilityMessage::create(
                abilityName: $abilityName,
                parameters: null,
                definition: null
            );
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

        // Assert - expectation verified by PHPUnit mock
    }
}
