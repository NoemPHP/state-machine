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
 * Acceptance Criterion: Validation handles nested object and array schemas
 *
 * Intent: Validates complex parameter structures with nested properties, supporting rich parameter types
 * Criticality: contract
 *
 * @see specs/features/abilities.yaml
 */
#[Group('abilities')]
#[Group('schema-validation')]
class ValidatesNestedStructuresTest extends TestCase
{
    public function testValidatesNestedObjectSchema(): void
    {
        // Arrange
        $abilityName = 'nested-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'email'],
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

        // Valid nested object
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'user' => [
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act - should not throw
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertNotNull($result);
    }

    public function testThrowsOnInvalidNestedObjectProperty(): void
    {
        // Arrange
        $abilityName = 'nested-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'address' => [
                    'type' => 'object',
                    'properties' => [
                        'street' => ['type' => 'string'],
                        'zipCode' => ['type' => 'integer'],
                    ],
                    'required' => ['street', 'zipCode'],
                ],
            ],
            'required' => ['address'],
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

        // Invalid - missing required nested property
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'address' => [
                    'street' => '123 Main St',
                    // missing zipCode
                ],
            ]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(SchemaValidationException::class);

        // Act
        $invokeAbility->call($params);
    }

    public function testValidatesArrayOfObjects(): void
    {
        // Arrange
        $abilityName = 'array-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'name'],
                    ],
                ],
            ],
            'required' => ['items'],
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

        // Valid array of objects
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'items' => [
                    ['id' => 1, 'name' => 'Item 1'],
                    ['id' => 2, 'name' => 'Item 2'],
                ],
            ]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertNotNull($result);
    }

    public function testThrowsOnInvalidArrayItemType(): void
    {
        // Arrange
        $abilityName = 'array-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['tags'],
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

        // Invalid - array contains non-string items
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'tags' => ['valid', 123, 'another'], // 123 is not a string
            ]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(SchemaValidationException::class);

        // Act
        $invokeAbility->call($params);
    }

    public function testValidatesDeeplyNestedStructures(): void
    {
        // Arrange
        $abilityName = 'deep-nested-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'company' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'departments' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'employees' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'integer'],
                                                'name' => ['type' => 'string'],
                                            ],
                                            'required' => ['id', 'name'],
                                        ],
                                    ],
                                ],
                                'required' => ['name', 'employees'],
                            ],
                        ],
                    ],
                    'required' => ['name', 'departments'],
                ],
            ],
            'required' => ['company'],
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

        // Valid deeply nested structure
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'company' => [
                    'name' => 'Acme Corp',
                    'departments' => [
                        [
                            'name' => 'Engineering',
                            'employees' => [
                                ['id' => 1, 'name' => 'Alice'],
                                ['id' => 2, 'name' => 'Bob'],
                            ],
                        ],
                    ],
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
