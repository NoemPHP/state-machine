<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Chain;

use Noem\State\Feature\Abilities\AbilityRegistry;
use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: InvokeAbility constructs with AbilityRegistry dependency
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class ConstructsWithRegistryTest extends TestCase
{
    public function testAcceptsAbilityRegistry(): void
    {
        // Arrange
        $registry = $this->createMock(AbilityRegistry::class);

        // Act
        $invokeAbility = new InvokeAbility($registry);

        // Assert - constructor should not throw
        $this->assertInstanceOf(InvokeAbility::class, $invokeAbility);
    }

    public function testRequiresRegistry(): void
    {
        // Expect exception when no registry provided
        $this->expectException(\TypeError::class);

        // Act - try to construct without registry
        new InvokeAbility();
    }
}
