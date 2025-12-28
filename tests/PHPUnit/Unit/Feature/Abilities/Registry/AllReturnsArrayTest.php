<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Registry;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityRegistry.all() returns array of all AbilityDefinitions
 *
 * Intent: Provides complete ability set for enumeration,
 * enabling meta-reflexive discovery
 *
 * Criticality: contract
 */
#[Group('abilities')]
#[Group('ability-registry')]
class AllReturnsArrayTest extends TestCase
{
    public function testAllReturnsArrayOfAllAbilityDefinitions(): void
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

        $ability3 = new AbilityDefinition(
            name: 'ability-three',
            description: 'Third ability',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): string => 'three'
        );

        $registry->register($ability1);
        $registry->register($ability2);
        $registry->register($ability3);

        // Get all abilities
        $allAbilities = $registry->all();

        // Verify it returns an array
        $this->assertIsArray($allAbilities);

        // Verify the array contains all registered abilities
        $this->assertCount(3, $allAbilities);
        $this->assertContains($ability1, $allAbilities);
        $this->assertContains($ability2, $allAbilities);
        $this->assertContains($ability3, $allAbilities);
    }

    public function testAllReturnsEmptyArrayWhenNoAbilitiesRegistered(): void
    {
        $registry = new AbilityRegistry();

        $allAbilities = $registry->all();

        // Verify it returns an empty array
        $this->assertIsArray($allAbilities);
        $this->assertEmpty($allAbilities);
    }

    public function testAllReturnsArrayWithAbilityDefinitionInstances(): void
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

        $allAbilities = $registry->all();

        // Verify all elements are AbilityDefinition instances
        foreach ($allAbilities as $ability) {
            $this->assertInstanceOf(AbilityDefinition::class, $ability);
        }
    }
}
