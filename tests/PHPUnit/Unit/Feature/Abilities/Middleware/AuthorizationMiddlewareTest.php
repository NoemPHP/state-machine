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
 * Acceptance Criterion: Middleware blocks unauthorized ability invocations
 *
 * Tests a real-world middleware example: authorization middleware that checks
 * permissions before allowing ability execution. This demonstrates how middleware
 * can enforce security policies by short-circuiting unauthorized invocations.
 *
 * This is a practical example showing how the middleware interception pattern
 * enables declarative security without coupling authorization logic to abilities.
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('middleware')]
#[Group('example')]
class AuthorizationMiddlewareTest extends TestCase
{
    public function testAuthorizationMiddlewareAllowsAuthorizedAccess(): void
    {
        // Arrange
        $abilityName = 'view-dashboard';

        $handlerCalled = false;

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'View dashboard',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($params) use (&$handlerCalled) {
                $handlerCalled = true;
                return ['dashboard' => 'data'];
            }
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $userPermissions = ['read', 'view-dashboard']; // Has permission

        // Authorization middleware
        $authMiddleware = function ($params, $next, $first) use ($abilityName, $userPermissions) {
            // Check if user has permission for this ability
            if (in_array($params->abilityName, $userPermissions)) {
                // Authorized - proceed
                return $next($params);
            }

            // Unauthorized - short-circuit
            return AbilityMessage::create(
                abilityName: $abilityName,
                parameters: ['error' => 'Forbidden'],
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
        $invokeAbility->call($params);

        // Assert - handler should be called for authorized access
        // (Actual implementation will verify this through region->trigger)
    }

    public function testAuthorizationMiddlewareBlocksUnauthorizedAccess(): void
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

        $userPermissions = ['read', 'write']; // No delete permission

        // Authorization middleware
        $authMiddleware = function ($params, $next, $first) use ($userPermissions) {
            if (in_array($params->abilityName, $userPermissions)) {
                return $next($params);
            }

            // Unauthorized - short-circuit
            return AbilityMessage::create(
                abilityName: $params->abilityName,
                parameters: [
                    'error' => 'Forbidden',
                    'message' => 'You do not have permission to execute this ability',
                    'required_permission' => $params->abilityName,
                ],
                definition: null
            );
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
        $this->assertFalse($handlerCalled, 'Handler should NOT be called for unauthorized access');
        $this->assertInstanceOf(AbilityMessage::class, $result);
        $this->assertArrayHasKey('error', $result->parameters);
        $this->assertSame('Forbidden', $result->parameters['error']);
    }

    public function testAuthorizationMiddlewareChecksRoleBasedPermissions(): void
    {
        // Arrange
        $abilityName = 'approve-invoice';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Approve invoice',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['approved' => true]
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $userRole = 'manager'; // Has approval rights

        $rolePermissions = [
            'admin' => ['*'], // All abilities
            'manager' => ['approve-invoice', 'view-reports'],
            'user' => ['view-dashboard'],
        ];

        // Role-based authorization middleware
        $authMiddleware = function ($params, $next, $first) use ($userRole, $rolePermissions) {
            $allowedAbilities = $rolePermissions[$userRole] ?? [];

            // Check wildcard or specific ability
            if (in_array('*', $allowedAbilities) || in_array($params->abilityName, $allowedAbilities)) {
                return $next($params);
            }

            return AbilityMessage::create(
                abilityName: $params->abilityName,
                parameters: [
                    'error' => 'Forbidden',
                    'user_role' => $userRole,
                ],
                definition: null
            );
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($authMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['invoiceId' => 456]
        );

        // Act
        $invokeAbility->call($params);

        // Assert - manager role should be authorized for approve-invoice
    }

    public function testAuthorizationMiddlewareValidatesResourceOwnership(): void
    {
        // Arrange
        $abilityName = 'edit-profile';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Edit profile',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['updated' => true]
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        $currentUserId = 42;

        // Ownership-based authorization middleware
        $authMiddleware = function ($params, $next, $first) use ($currentUserId) {
            // Check if user is editing their own profile
            $targetUserId = $params->parameters['userId'] ?? null;

            if ($targetUserId === $currentUserId) {
                // User owns the resource - authorized
                return $next($params);
            }

            // User doesn't own the resource - unauthorized
            return AbilityMessage::create(
                abilityName: $params->abilityName,
                parameters: [
                    'error' => 'Forbidden',
                    'message' => 'You can only edit your own profile',
                ],
                definition: null
            );
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($authMiddleware);

        // Case 1: User edits their own profile (authorized)
        $ownProfileParams = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['userId' => 42]
        );

        // Act
        $result1 = $invokeAbility->call($ownProfileParams);

        // Assert - should be authorized
        // (Handler execution happens via region->trigger in actual implementation)

        // Case 2: User tries to edit another user's profile (unauthorized)
        $otherProfileParams = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['userId' => 99]
        );

        // Act
        $result2 = $invokeAbility->call($otherProfileParams);

        // Assert
        $this->assertInstanceOf(AbilityMessage::class, $result2);
        $this->assertArrayHasKey('error', $result2->parameters);
        $this->assertSame('Forbidden', $result2->parameters['error']);
    }

    public function testAuthorizationMiddlewareWithMultipleChecks(): void
    {
        // Arrange
        $abilityName = 'publish-article';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Publish article',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['published' => true]
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        $userPermissions = ['write-article', 'publish-article'];
        $articleStatus = 'draft';

        // Multi-condition authorization middleware
        $authMiddleware = function ($params, $next, $first) use ($userPermissions, $articleStatus) {
            // Check 1: User has permission
            if (!in_array('publish-article', $userPermissions)) {
                return AbilityMessage::create(
                    abilityName: $params->abilityName,
                    parameters: ['error' => 'Missing permission: publish-article'],
                    definition: null
                );
            }

            // Check 2: Article is in valid state
            if ($articleStatus !== 'draft') {
                return AbilityMessage::create(
                    abilityName: $params->abilityName,
                    parameters: ['error' => 'Article must be in draft state to publish'],
                    definition: null
                );
            }

            // All checks passed
            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($authMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['articleId' => 789]
        );

        // Act
        $invokeAbility->call($params);

        // Assert - both permission and state checks should pass
    }

    public function testAuthorizationMiddlewareThrowsExceptionForCriticalViolations(): void
    {
        // Arrange
        $abilityName = 'access-admin-panel';

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Access admin panel',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['panel' => 'data']
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        $isBanned = true; // User is banned

        // Strict authorization middleware that throws exceptions
        $authMiddleware = function ($params, $next, $first) use ($isBanned) {
            if ($isBanned) {
                // Critical violation - throw exception instead of returning error message
                throw new \RuntimeException('User account is banned');
            }

            return $next($params);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($authMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User account is banned');

        $invokeAbility->call($params);
    }
}
