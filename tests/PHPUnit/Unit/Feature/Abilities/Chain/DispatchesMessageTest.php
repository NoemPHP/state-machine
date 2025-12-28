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
 * Acceptance Criterion: InvokeAbility.call() dispatches AbilityMessage via region.trigger()
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class DispatchesMessageTest extends TestCase
{
    public function testDispatchesMessageViaRegionTrigger(): void
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
        $region->expects($this->once())
            ->method('trigger')
            ->with($this->isInstanceOf(AbilityMessage::class))
            ->willReturn(new \stdClass());

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $invokeAbility->call($params);
    }

    public function testDispatchesMessageToCorrectRegion(): void
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

        $targetRegion = $this->createMock(Region::class);
        $targetRegion->expects($this->once())
            ->method('trigger')
            ->willReturn(new \stdClass());

        $params = new InvokeAbilityParams(
            region: $targetRegion,
            abilityName: $abilityName,
            parameters: ['data' => 'test']
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $invokeAbility->call($params);

        // Assert - expectation verified by mock
    }

    public function testDispatchedMessageContainsAbilityData(): void
    {
        // Arrange
        $abilityName = 'test-ability';
        $parameters = ['key' => 'value'];
        $definition = new AbilityDefinition(
            name: $abilityName,
            description: 'Test ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn($params) => []
        );

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn($definition);

        $dispatchedMessage = null;
        $region = $this->createMock(Region::class);
        $region->expects($this->once())
            ->method('trigger')
            ->willReturnCallback(function ($message) use (&$dispatchedMessage) {
                $dispatchedMessage = $message;
                return new \stdClass();
            });

        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: $parameters
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $invokeAbility->call($params);

        // Assert
        $this->assertInstanceOf(AbilityMessage::class, $dispatchedMessage);
        $this->assertSame($abilityName, $dispatchedMessage->abilityName);
        $this->assertSame($parameters, $dispatchedMessage->parameters);
    }
}
