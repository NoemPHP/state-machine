<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Registry;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityRegistry.get() retrieves AbilityDefinition by name
 *
 * Intent: Looks up registered ability, returning definition or null if not found,
 * supporting existence checking
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-registry')]
class GetRetrievesDefinitionTest extends TestCase
{
    public function testGetRetrievesRegisteredAbilityByName(): void
    {
        $registry = new AbilityRegistry();

        // Create and register an ability
        $definition = new AbilityDefinition(
            name: 'fetch-data',
            description: 'Fetches data from source',
            parameterSchema: ['type' => 'object'],
            responseSchema: ['type' => 'array'],
            handler: fn(mixed $params): array => ['data' => 'result']
        );

        $registry->register($definition);

        // Retrieve the ability using get()
        $retrieved = $registry->get('fetch-data');

        // Verify it returns the same definition
        $this->assertSame($definition, $retrieved);
    }

    public function testGetReturnsCorrectAbilityWhenMultipleRegistered(): void
    {
        $registry = new AbilityRegistry();

        // Register multiple abilities
        $ability1 = new AbilityDefinition(
            name: 'ability-one',
            description: 'First ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): string => 'one'
        );

        $ability2 = new AbilityDefinition(
            name: 'ability-two',
            description: 'Second ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): string => 'two'
        );

        $registry->register($ability1);
        $registry->register($ability2);

        // Verify get() returns the correct ability
        $this->assertSame($ability1, $registry->get('ability-one'));
        $this->assertSame($ability2, $registry->get('ability-two'));
    }

    public function testGetReturnsAbilityDefinitionInstance(): void
    {
        $registry = new AbilityRegistry();

        $definition = new AbilityDefinition(
            name: 'test-ability',
            description: 'Test',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): mixed => null
        );

        $registry->register($definition);

        $retrieved = $registry->get('test-ability');

        // Verify the return type is AbilityDefinition
        $this->assertInstanceOf(AbilityDefinition::class, $retrieved);
    }
}
