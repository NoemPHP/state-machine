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
 * Acceptance Criterion: InvokeAbility.call() returns created AbilityMessage
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class ReturnsMessageTest extends TestCase
{
    public function testReturnsAbilityMessage(): void
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
    }

    public function testReturnedMessageHasCorrelationId(): void
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

        // Assert - message should have correlation ID for then() chaining
        $this->assertInstanceOf(AbilityMessage::class, $result);
        $this->assertNotEmpty($result->correlationId);
    }

    public function testReturnedMessageSameAsDispatched(): void
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
            parameters: null
        );

        $invokeAbility = new InvokeAbility($registry);

        // Act
        $returnedMessage = $invokeAbility->call($params);

        // Assert - returned message should be same as dispatched message
        $this->assertSame($dispatchedMessage, $returnedMessage);
    }
}
