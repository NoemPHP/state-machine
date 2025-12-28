<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Chain;

use Noem\State\Feature\Abilities\AbilityNotFoundException;
use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Feature\Abilities\Chains\Params\InvokeAbility as InvokeAbilityParams;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: InvokeAbility.call() throws AbilityNotFoundException for unknown ability
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class ThrowsNotFoundExceptionTest extends TestCase
{
    public function testThrowsWhenAbilityNotFound(): void
    {
        // Arrange
        $abilityName = 'non-existent-ability';

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->expects($this->once())
            ->method('get')
            ->with($abilityName)
            ->willReturn(null);

        $region = $this->createMock(Region::class);
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(AbilityNotFoundException::class);

        // Act
        $invokeAbility->call($params);
    }

    public function testExceptionIncludesAbilityName(): void
    {
        // Arrange
        $abilityName = 'missing-ability';

        $registry = $this->createMock(AbilityRegistry::class);
        $registry->method('get')->willReturn(null);

        $region = $this->createMock(Region::class);
        $params = new InvokeAbilityParams(
            region: $region,
            abilityName: $abilityName,
            parameters: null
        );

        $invokeAbility = new InvokeAbility($registry);

        // Expect
        $this->expectException(AbilityNotFoundException::class);
        $this->expectExceptionMessage($abilityName);

        // Act
        $invokeAbility->call($params);
    }
}
