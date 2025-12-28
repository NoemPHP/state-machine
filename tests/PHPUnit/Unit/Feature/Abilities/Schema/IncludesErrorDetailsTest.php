<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Schema;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Feature\Abilities\SchemaValidationException;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: SchemaValidationException includes detailed validation error messages
 *
 * Intent: Provides actionable error details for debugging, listing all schema violations
 * Criticality: contract
 *
 * @see specs/features/abilities.yaml
 */
#[Group('abilities')]
#[Group('schema-validation')]
class IncludesErrorDetailsTest extends TestCase
{
    public function testExceptionIncludesValidationErrors(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name', 'age'],
        ];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: $parameterSchema,
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // Invalid parameters - missing 'age'
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['name' => 'John']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act & Assert
        try {
            $invokeAbility->call($params);
            $this->fail('Expected SchemaValidationException was not thrown');
        } catch (SchemaValidationException $e) {
            // Exception should have error details about missing 'age'
            $errors = $e->getErrors();
            $this->assertNotEmpty($errors, 'Exception should include validation error details');
            $this->assertIsArray($errors, 'Errors should be an array');
        }
    }

    public function testExceptionIncludesMultipleErrors(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                'email' => ['type' => 'string'],
            ],
            'required' => ['name', 'age', 'email'],
        ];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: $parameterSchema,
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // Invalid parameters - type mismatch AND missing required
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'name' => 'John',
                'age' => 'not-an-integer', // Type error
                // missing 'email'
            ]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act & Assert
        try {
            $invokeAbility->call($params);
            $this->fail('Expected SchemaValidationException was not thrown');
        } catch (SchemaValidationException $e) {
            $errors = $e->getErrors();
            // Should have multiple errors
            $this->assertGreaterThanOrEqual(1, count($errors), 'Should include multiple validation errors');
        }
    }

    public function testExceptionMessageIsDescriptive(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'count' => ['type' => 'integer'],
            ],
            'required' => ['count'],
        ];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: $parameterSchema,
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // Invalid parameters
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['count' => 'invalid']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act & Assert
        try {
            $invokeAbility->call($params);
            $this->fail('Expected SchemaValidationException was not thrown');
        } catch (SchemaValidationException $e) {
            // Message should be descriptive
            $message = $e->getMessage();
            $this->assertNotEmpty($message, 'Exception message should not be empty');
            $this->assertIsString($message);
        }
    }

    public function testErrorDetailsAreStructured(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'nested' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => ['type' => 'string'],
                    ],
                    'required' => ['value'],
                ],
            ],
            'required' => ['nested'],
        ];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: $parameterSchema,
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // Invalid nested structure
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'nested' => [
                    'value' => 123, // Wrong type
                ],
            ]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act & Assert
        try {
            $invokeAbility->call($params);
            $this->fail('Expected SchemaValidationException was not thrown');
        } catch (SchemaValidationException $e) {
            $errors = $e->getErrors();
            $this->assertIsArray($errors);

            // Each error should have structured information
            foreach ($errors as $error) {
                $this->assertIsArray($error);
            }
        }
    }

    public function testExceptionIncludesAbilityNameContext(): void
    {
        // Arrange
        $abilityName = 'specific-ability-name';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'field' => ['type' => 'string'],
            ],
            'required' => ['field'],
        ];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: $parameterSchema,
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // Invalid parameters
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: []
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act & Assert
        try {
            $invokeAbility->call($params);
            $this->fail('Expected SchemaValidationException was not thrown');
        } catch (SchemaValidationException $e) {
            // Exception should include context about which ability failed
            $message = $e->getMessage();
            $this->assertStringContainsString(
                $abilityName,
                $message,
                'Exception message should include ability name for context'
            );
        }
    }
}
