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
 * Acceptance Criterion: Validation throws SchemaValidationException for missing required properties
 *
 * Intent: Throws SchemaValidationException when required parameters missing, providing clear error
 * Criticality: contract
 *
 * @see specs/features/abilities.yaml
 */
#[Group('abilities')]
#[Group('schema-validation')]
class EnforcesRequiredPropertiesTest extends TestCase
{
    public function testThrowsOnMissingRequiredProperty(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
            ],
            'required' => ['name', 'email'],
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

        // Missing 'email' required property
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['name' => 'John Doe']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(SchemaValidationException::class);

        // Act
        $invokeAbility->call($params);
    }

    public function testThrowsOnAllRequiredPropertiesMissing(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'username' => ['type' => 'string'],
                'password' => ['type' => 'string'],
            ],
            'required' => ['username', 'password'],
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

        // No parameters provided
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: []
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(SchemaValidationException::class);

        // Act
        $invokeAbility->call($params);
    }

    public function testThrowsOnNullInsteadOfRequiredProperty(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'string'],
            ],
            'required' => ['id'],
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

        // Null value for required property
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['id' => null]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(SchemaValidationException::class);

        // Act
        $invokeAbility->call($params);
    }

    public function testPassesWhenAllRequiredPropertiesPresent(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                'optional' => ['type' => 'string'],
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
        $region->method('trigger')->willReturn(new \stdClass());

        // All required properties present
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: [
                'name' => 'John',
                'age' => 30,
            ]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act - should not throw
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertNotNull($result);
    }
}
