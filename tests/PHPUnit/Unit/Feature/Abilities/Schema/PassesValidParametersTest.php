<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Schema;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Validation passes for parameters matching schema
 *
 * Intent: Allows invocation to proceed when parameters conform to schema, supporting normal flow
 * Criticality: contract
 *
 * @see specs/features/abilities.yaml
 */
#[Group('abilities')]
#[Group('schema-validation')]
class PassesValidParametersTest extends TestCase
{
    public function testPassesValidObjectParameters(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: $parameterSchema,
            responseSchema: [],
            handler: fn($params) => ['result' => 'success']
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        // Valid parameters matching schema
        $validParams = [
            'name' => 'John Doe',
            'age' => 30,
        ];

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $validParams
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act - should not throw
        $result = $invokeAbility->call($params);

        // Assert - validation passed, message created
        $this->assertInstanceOf(AbilityMessage::class, $result);
    }

    public function testPassesMinimalValidParameters(): void
    {
        // Arrange
        $abilityName = 'minimal-ability';
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
        $region->method('trigger')->willReturn(new \stdClass());

        // Minimal valid parameters
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['id' => 'test-123']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertInstanceOf(AbilityMessage::class, $result);
    }

    public function testPassesParametersWithOptionalProperties(): void
    {
        // Arrange
        $abilityName = 'optional-props-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'required_field' => ['type' => 'string'],
                'optional_field' => ['type' => 'integer'],
            ],
            'required' => ['required_field'],
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

        // Parameters with only required field (optional omitted)
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['required_field' => 'value']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertInstanceOf(AbilityMessage::class, $result);
    }
}
