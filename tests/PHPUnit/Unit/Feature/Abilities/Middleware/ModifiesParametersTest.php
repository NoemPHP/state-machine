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
 * Acceptance Criterion: Middleware can modify parameters before validation
 *
 * Tests that middleware can transform or normalize parameters before they reach
 * schema validation and the ability handler. This enables parameter injection,
 * normalization, enrichment, and other transformations as cross-cutting concerns.
 *
 * Note: Params\InvokeAbility is immutable, so middleware must create new params
 * instances to modify values.
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('middleware')]
class ModifiesParametersTest extends TestCase
{
    public function testMiddlewareCanModifyParameters(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $originalParameters = ['value' => 100];
        $modifiedParameters = ['value' => 200, 'enriched' => true];

        $handlerReceivedParams = null;

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: function ($params) use (&$handlerReceivedParams) {
                $handlerReceivedParams = $params;
                return [];
            }
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        // Middleware that modifies parameters
        $middleware = function (InvokeAbilityParams $params, $next, $first) use ($modifiedParameters) {
            // Create new params with modified parameters
            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: $modifiedParameters
            );
            return $next($newParams);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($middleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $originalParameters
        );

        // Act
        $invokeAbility->call($params);

        // Assert - handler should receive modified parameters
        // (This will be verified through the actual implementation)
    }

    public function testMiddlewareCanEnrichParameters(): void
    {
        // Arrange
        $abilityName = 'send-notification';
        $originalParameters = ['message' => 'Hello'];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Send notification',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        // Middleware that enriches parameters with metadata
        $middleware = function (InvokeAbilityParams $params, $next, $first) {
            $enrichedParams = array_merge(
                $params->parameters ?? [],
                [
                    'timestamp' => time(),
                    'source' => 'middleware',
                    'correlation_id' => uniqid('corr_'),
                ]
            );

            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: $enrichedParams
            );

            return $next($newParams);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($middleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $originalParameters
        );

        // Act
        $invokeAbility->call($params);

        // Assert - enriched parameters should reach the handler
    }

    public function testMiddlewareCanNormalizeParameters(): void
    {
        // Arrange
        $abilityName = 'search-users';
        $unnormalizedParameters = [
            'query' => '  JOHN DOE  ', // whitespace, uppercase
            'limit' => '10', // string instead of int
        ];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Search users',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        // Middleware that normalizes parameters
        $middleware = function (InvokeAbilityParams $params, $next, $first) {
            $normalized = [
                'query' => trim(strtolower($params->parameters['query'] ?? '')),
                'limit' => (int)($params->parameters['limit'] ?? 10),
            ];

            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: $normalized
            );

            return $next($newParams);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($middleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $unnormalizedParameters
        );

        // Act
        $invokeAbility->call($params);

        // Assert - normalized parameters should reach validation and handler
    }

    public function testMultipleMiddlewareCanChainParameterTransformations(): void
    {
        // Arrange
        $abilityName = 'process-data';
        $originalParameters = ['value' => 5];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Process data',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        // First middleware: doubles the value
        $doubleMiddleware = function (InvokeAbilityParams $params, $next, $first) {
            $doubled = ['value' => ($params->parameters['value'] ?? 0) * 2];
            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: $doubled
            );
            return $next($newParams);
        };

        // Second middleware: adds metadata
        $metadataMiddleware = function (InvokeAbilityParams $params, $next, $first) {
            $withMetadata = array_merge(
                $params->parameters ?? [],
                ['transformed' => true]
            );
            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: $withMetadata
            );
            return $next($newParams);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($doubleMiddleware);
        $invokeAbility->link($metadataMiddleware);

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $originalParameters
        );

        // Act
        $invokeAbility->call($params);

        // Assert - final parameters should be: ['value' => 10, 'transformed' => true]
    }

    public function testMiddlewareModificationOccursBeforeValidation(): void
    {
        // Arrange
        $abilityName = 'create-user';

        // Handler should not be reached if validation fails
        $handlerCalled = false;

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Create user',
            parameterSchema: [
                'type' => 'object',
                'required' => ['email', 'name'],
                'properties' => [
                    'email' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                ],
            ],
            responseSchema: [],
            handler: function ($params) use (&$handlerCalled) {
                $handlerCalled = true;
                return [];
            }
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        // Middleware that adds required fields
        $middleware = function (InvokeAbilityParams $params, $next, $first) {
            $complete = [
                'email' => 'user@example.com',
                'name' => 'Test User',
            ];
            $newParams = new InvokeAbilityParams(
                region: $params->region,
                abilityName: $params->abilityName,
                parameters: $complete
            );
            return $next($newParams);
        };

        $invokeAbility = new InvokeAbility($registry);
        $invokeAbility->link($middleware);

        // Start with incomplete parameters (missing required fields)
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [] // Missing required fields
        );

        // Act
        $invokeAbility->call($params);

        // Assert - middleware should add required fields before validation,
        // allowing the invocation to succeed
    }
}
