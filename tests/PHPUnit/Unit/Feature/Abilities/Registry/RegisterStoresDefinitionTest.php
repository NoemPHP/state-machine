<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Registry;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityRegistry.register() stores AbilityDefinition by name
 *
 * Intent: Adds ability to registry indexed by name, enabling lookup during invocation
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-registry')]
class RegisterStoresDefinitionTest extends TestCase
{
    public function testRegisterStoresAbilityDefinitionByName(): void
    {
        $registry = new AbilityRegistry();

        // Create an AbilityDefinition
        $definition = new AbilityDefinition(
            name: 'test-ability',
            description: 'Test ability description',
            parameterSchema: ['type' => 'object'],
            responseSchema: ['type' => 'object'],
            handler: fn(mixed $params): mixed => $params
        );

        // Register the ability
        $registry->register($definition);

        // Verify it was stored and can be retrieved by name
        $this->assertSame($definition, $registry['test-ability']);
    }

    public function testRegisterUsesAbilityNameAsKey(): void
    {
        $registry = new AbilityRegistry();

        $definition = new AbilityDefinition(
            name: 'my-ability',
            description: 'Description',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): mixed => null
        );

        $registry->register($definition);

        // Verify the ability is accessible via its name property
        $this->assertTrue(isset($registry['my-ability']));
        $this->assertSame('my-ability', $registry['my-ability']->name);
    }
}
