<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Chain;

use Noem\State\Feature\Abilities\Chains\InvokeAbility;
use Noem\State\Middleware\Chain;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: InvokeAbility extends Chain with Params\InvokeAbility input type
 *
 * @see specs/features/abilities.yaml
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('chain')]
class ExtendsChainTest extends TestCase
{
    public function testExtendsChainBaseClass(): void
    {
        // Assert - InvokeAbility extends Chain
        $this->assertTrue(
            is_subclass_of(InvokeAbility::class, Chain::class),
            'InvokeAbility must extend Chain base class'
        );
    }

    public function testIsInstanceOfChain(): void
    {
        // Arrange
        $registry = $this->createMock(\Noem\State\Feature\Abilities\AbilityRegistry::class);

        // Act
        $invokeAbility = new InvokeAbility($registry);

        // Assert
        $this->assertInstanceOf(Chain::class, $invokeAbility);
    }
}
