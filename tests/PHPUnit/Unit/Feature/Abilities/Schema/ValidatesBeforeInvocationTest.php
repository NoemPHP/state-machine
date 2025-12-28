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
 * Acceptance Criterion: InvokeAbility validates parameters against parameterSchema before creating message
 *
 * Intent: Enforces schema compliance before invocation, catching invalid parameters early
 * Criticality: contract
 *
 * @see specs/features/abilities.yaml
 */
#[Group('abilities')]
#[Group('schema-validation')]
class ValidatesBeforeInvocationTest extends TestCase
{
    public function testValidatesParametersBeforeCreatingMessage(): void
    {
        // Arrange - schema requires 'name' property
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
            handler: fn($params) => ['result' => 'success']
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

        // Expect - validation should throw BEFORE message creation or dispatch
        $this->expectException(SchemaValidationException::class);

        // Act - should fail during validation, not dispatch
        $invokeAbility->call($params);
    }

    public function testValidationOccursBeforeDispatch(): void
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

        // Region should NEVER be called if validation fails
        $region = $this->createMock(Region::class);
        $region->expects($this->never())
            ->method('trigger');

        // Invalid parameters - wrong type
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: ['count' => 'not-an-integer']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(SchemaValidationException::class);

        // Act
        $invokeAbility->call($params);
    }
}
