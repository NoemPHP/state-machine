<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Chain;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityMessage;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: InvokeAbility.call() creates AbilityMessage with abilityName and parameters
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class CreatesAbilityMessageTest extends TestCase
{
    public function testCreatesAbilityMessageWithName(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertInstanceOf(AbilityMessage::class, $result);
        $this->assertSame($abilityName, $result->abilityName);
    }

    public function testCreatesAbilityMessageWithParameters(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value', 'number' => 42];

        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $parameters
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $result = $invokeAbility->call($params);

        // Assert
        $this->assertInstanceOf(AbilityMessage::class, $result);
        $this->assertSame($parameters, $result->parameters);
    }

    public function testIncludesDefinitionInMessage(): void
    {
        // Arrange - from spec line 207-210
        $abilityName = 'test-ability';
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $region = $this->createMock(Region::class);
        $region->method('trigger')->willReturn(new \stdClass());

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $result = $invokeAbility->call($params);

        // Assert - message should include definition reference
        $this->assertInstanceOf(AbilityMessage::class, $result);
        $this->assertSame($definition, $result->definition);
    }
}
