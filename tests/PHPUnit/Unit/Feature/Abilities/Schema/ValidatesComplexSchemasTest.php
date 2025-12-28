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
 * Acceptance Criterion: Validation handles complex schemas with anyOf, oneOf, allOf
 *
 * Intent: Supports advanced JSON Schema features for flexible validation
 * Criticality: contract
 *
 * @see specs/features/abilities.yaml
 */
#[Group('abilities')]
#[Group('schema-validation')]
class ValidatesComplexSchemasTest extends TestCase
{
    public function testValidatesAnyOfSchema(): void
    {
        // Arrange
        $abilityName = 'anyof-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'value' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'integer'],
                    ],
                ],
            ],
            'required' => ['value'],
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

        // Should accept string (first option in anyOf)
        $params1 = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['value' => 'test']
        );

        $invokeAbility = new InvokeAbility($registry);
        $result1 = $invokeAbility->call($params1);
        $this->assertNotNull($result1);

        // Should also accept integer (second option in anyOf)
        $params2 = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['value' => 42]
        );

        $result2 = $invokeAbility->call($params2);
        $this->assertNotNull($result2);
    }

    public function testRejectsAnyOfSchemaViolation(): void
    {
        // Arrange
        $abilityName = 'anyof-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'value' => [
                    'anyOf' => [
                        ['type' => 'string'],
                        ['type' => 'integer'],
                    ],
                ],
            ],
            'required' => ['value'],
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

        // Should reject boolean (not in anyOf)
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['value' => true]
        );

        $invokeAbility = new InvokeAbility($registry);

        $this->expectException(SchemaValidationException::class);
        $invokeAbility->call($params);
    }

    public function testValidatesOneOfSchema(): void
    {
        // Arrange
        $abilityName = 'oneof-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'identifier' => [
                    'oneOf' => [
                        [
                            'type' => 'object',
                            'properties' => [
                                'email' => ['type' => 'string'],
                            ],
                            'required' => ['email'],
                        ],
                        [
                            'type' => 'object',
                            'properties' => [
                                'phone' => ['type' => 'string'],
                            ],
                            'required' => ['phone'],
                        ],
                    ],
                ],
            ],
            'required' => ['identifier'],
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

        // Should accept email identifier
        $params1 = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['identifier' => ['email' => 'test@example.com']]
        );

        $invokeAbility = new InvokeAbility($registry);
        $result1 = $invokeAbility->call($params1);
        $this->assertNotNull($result1);

        // Should accept phone identifier
        $params2 = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['identifier' => ['phone' => '555-1234']]
        );

        $result2 = $invokeAbility->call($params2);
        $this->assertNotNull($result2);
    }

    public function testRejectsOneOfSchemaBothMatch(): void
    {
        // Arrange - oneOf requires EXACTLY one match, not multiple
        $abilityName = 'oneof-exclusive-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'oneOf' => [
                        [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                            ],
                            'required' => ['id'],
                        ],
                        [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                            ],
                            'required' => ['id', 'name'],
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

        // This matches BOTH schemas in oneOf, which violates oneOf constraint
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['data' => ['id' => 1, 'name' => 'test']]
        );

        $invokeAbility = new InvokeAbility($registry);

        $this->expectException(SchemaValidationException::class);
        $invokeAbility->call($params);
    }

    public function testValidatesAllOfSchema(): void
    {
        // Arrange
        $abilityName = 'allof-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'allOf' => [
                        [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                            ],
                            'required' => ['name'],
                        ],
                        [
                            'type' => 'object',
                            'properties' => [
                                'email' => ['type' => 'string'],
                            ],
                            'required' => ['email'],
                        ],
                    ],
                ],
            ],
            'required' => ['user'],
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

        // Must satisfy ALL schemas in allOf
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'user' => [
                    'name' => 'John',
                    'email' => 'john@example.com',
                ],
            ]
        );

        $invokeAbility = new InvokeAbility($registry);
        $result = $invokeAbility->call($params);
        $this->assertNotNull($result);
    }

    public function testRejectsAllOfSchemaPartialMatch(): void
    {
        // Arrange
        $abilityName = 'allof-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'allOf' => [
                        [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                            ],
                            'required' => ['name'],
                        ],
                        [
                            'type' => 'object',
                            'properties' => [
                                'email' => ['type' => 'string'],
                            ],
                            'required' => ['email'],
                        ],
                    ],
                ],
            ],
            'required' => ['user'],
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

        // Only satisfies first schema, missing email
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['user' => ['name' => 'John']]
        );

        $invokeAbility = new InvokeAbility($registry);

        $this->expectException(SchemaValidationException::class);
        $invokeAbility->call($params);
    }

    public function testValidatesNestedComplexSchemas(): void
    {
        // Arrange
        $abilityName = 'complex-nested-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'filters' => [
                    'type' => 'array',
                    'items' => [
                        'oneOf' => [
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'string'],
                                    'value' => ['type' => 'string'],
                                ],
                                'required' => ['type', 'value'],
                            ],
                            [
                                'type' => 'object',
                                'properties' => [
                                    'type' => ['const' => 'number'],
                                    'value' => ['type' => 'integer'],
                                ],
                                'required' => ['type', 'value'],
                            ],
                        ],
                    ],
                ],
            ],
            'required' => ['filters'],
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

        // Array with mixed filter types
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'filters' => [
                    ['type' => 'string', 'value' => 'test'],
                    ['type' => 'number', 'value' => 42],
                ],
            ]
        );

        $invokeAbility = new InvokeAbility($registry);
        $result = $invokeAbility->call($params);
        $this->assertNotNull($result);
    }
}
