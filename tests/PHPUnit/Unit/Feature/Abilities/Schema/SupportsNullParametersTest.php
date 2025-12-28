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
 * Acceptance Criterion: Validation handles null parameters when schema allows
 *
 * Intent: Supports nullable parameters and null values where schema permits
 * Criticality: contract
 *
 * @see specs/features/abilities.yaml
 */
#[Group('abilities')]
#[Group('schema-validation')]
class SupportsNullParametersTest extends TestCase
{
    public function testAcceptsNullWhenSchemaAllowsNull(): void
    {
        // Arrange
        $abilityName = 'nullable-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'optionalField' => [
                    'type' => ['string', 'null'],
                ],
            ],
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
        $region->method('trigger')->willReturn(new \stdClass());

        // Null value for nullable field
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['optionalField' => null]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act - should not throw
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertNotNull($result);
    }

    public function testRejectsNullWhenSchemaDisallowsNull(): void
    {
        // Arrange
        $abilityName = 'non-nullable-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'requiredField' => ['type' => 'string'],
            ],
            'required' => ['requiredField'],
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

        // Null value for non-nullable field
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['requiredField' => null]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(SchemaValidationException::class);

        // Act
        $invokeAbility->call($params);
    }

    public function testAcceptsNullParametersObjectWhenNotRequired(): void
    {
        // Arrange
        $abilityName = 'optional-params-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'optionalField' => ['type' => 'string'],
            ],
            // No required fields
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
        $region->method('trigger')->willReturn(new \stdClass());

        // Null parameters (from InvokeAbilityParams default)
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act - should not throw for null parameters when schema has no required fields
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertNotNull($result);
    }

    public function testAcceptsEmptyObjectWhenNoRequiredFields(): void
    {
        // Arrange
        $abilityName = 'empty-ok-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'optional1' => ['type' => 'string'],
                'optional2' => ['type' => 'integer'],
            ],
            // No required array
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
        $region->method('trigger')->willReturn(new \stdClass());

        // Empty parameters object
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: []
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertNotNull($result);
    }

    public function testAcceptsNullInNestedStructure(): void
    {
        // Arrange
        $abilityName = 'nested-nullable-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'object',
                    'properties' => [
                        'value' => [
                            'type' => ['string', 'null'],
                        ],
                    ],
                ],
            ],
            'required' => ['data'],
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
        $region->method('trigger')->willReturn(new \stdClass());

        // Null value in nested structure
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'data' => [
                    'value' => null,
                ],
            ]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertNotNull($result);
    }
}
