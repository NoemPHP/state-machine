<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Chain;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: InvokeAbility.call() looks up AbilityDefinition from registry by name
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class LookupsDefinitionTest extends TestCase
{
    public function testLookupsAbilityByName(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => ['result' => 'success']
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->expects($this->once())
            ->method('get')
            ->with($abilityName)
            ->willReturn($definition);

        $region = $this->createMock(Region::class);
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $invokeAbility->call($params);
    }

    public function testUsesAbilityNameFromParams(): void
    {
        // Arrange
        $expectedName = 'specific-ability';
        $definition = new AbilityDefinition(
            name: $expectedName,
            description: 'Specific ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->expects($this->once())
            ->method('get')
            ->with($expectedName)
            ->willReturn($definition);

        $region = $this->createMock(Region::class);
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $expectedName,
            parameters: ['data' => 'test']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $invokeAbility->call($params);
    }
}
