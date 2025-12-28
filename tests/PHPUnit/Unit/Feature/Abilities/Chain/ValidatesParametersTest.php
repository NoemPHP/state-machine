<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Chain;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Feature\Abilities\SchemaValidationException;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: InvokeAbility.call() validates parameters against parameterSchema
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class ValidatesParametersTest extends TestCase
{
    public function testValidatesParametersAgainstSchema(): void
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
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);

        // Invalid parameters - missing required 'name'
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['age' => 25]
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(SchemaValidationException::class);

        // Act
        $invokeAbility->call($params);
    }

    public function testAcceptsValidParameters(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameterSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
            'required' => ['name'],
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

        // Valid parameters
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['name' => 'John']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act - should not throw
        $result = $invokeAbility->call($params);

        // Assert - if we get here, validation passed
        $this->assertNotNull($result);
    }

    public function testSkipsValidationForEmptySchema(): void
    {
        // Arrange
        $abilityName = 'no-schema-ability';
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Ability without schema',
            parameterSchema: [], // Empty schema
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        // Any parameters should be accepted
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['anything' => 'goes', 'no' => 'validation']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act - should not throw
        $result = $invokeAbility->call($params);

        // Assert - validation was skipped
        $this->assertNotNull($result);
    }
}
