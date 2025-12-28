<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Registry;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityRegistry.get() returns null for unknown ability name
 *
 * Intent: Provides safe lookup without exceptions, enabling existence checks before invocation
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-registry')]
class GetReturnsNullTest extends TestCase
{
    public function testGetReturnsNullForNonExistentAbility(): void
    {
        $registry = new AbilityRegistry();

        // Try to get an ability that was never registered
        $result = $registry->get('non-existent-ability');

        // Verify it returns null
        $this->assertNull($result);
    }

    public function testGetReturnsNullForEmptyRegistry(): void
    {
        $registry = new AbilityRegistry();

        // Get from empty registry
        $result = $registry->get('any-ability');

        $this->assertNull($result);
    }

    public function testGetReturnsNullAfterRegisteringDifferentAbility(): void
    {
        $registry = new AbilityRegistry();

        // Register one ability
        $definition = new AbilityDefinition(
            name: 'existing-ability',
            description: 'An existing ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): mixed => null
        );

        $registry->register($definition);

        // Try to get a different ability
        $result = $registry->get('different-ability');

        // Verify it returns null for the non-existent one
        $this->assertNull($result);

        // Verify the existing one still works
        $this->assertSame($definition, $registry->get('existing-ability'));
    }

    public function testGetDoesNotThrowExceptionForUnknownAbility(): void
    {
        $registry = new AbilityRegistry();

        // This should not throw an exception
        $result = $registry->get('unknown-ability');

        // Verify it safely returns null instead of throwing
        $this->assertNull($result);
    }
}
