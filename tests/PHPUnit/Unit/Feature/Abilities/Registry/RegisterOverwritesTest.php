<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Abilities\Registry;

use Noem\State\Feature\Abilities\AbilityDefinition;
use Noem\State\Feature\Abilities\AbilityRegistry;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AbilityRegistry.register() overwrites existing ability with same name
 *
 * Intent: Allows ability redefinition with explicit overwrite semantics,
 * supporting runtime customization
 *
 * Criticality: constraint
 */
#[Group('abilities')]
#[Group('ability-registry')]
class RegisterOverwritesTest extends TestCase
{
    public function testRegisterOverwritesExistingAbilityWithSameName(): void
    {
        $registry = new AbilityRegistry();

        // Register initial ability
        $original = new AbilityDefinition(
            name: 'my-ability',
            description: 'Original description',
            parameterSchema: ['type' => 'string'],
            responseSchema: ['type' => 'string'],
            handler: fn(mixed $params): string => 'original'
        );

        $registry->register($original);

        // Register new ability with same name
        $replacement = new AbilityDefinition(
            name: 'my-ability',
            description: 'Updated description',
            parameterSchema: ['type' => 'number'],
            responseSchema: ['type' => 'number'],
            handler: fn(mixed $params): int => 42
        );

        $registry->register($replacement);

        // Verify the new ability replaced the original
        $retrieved = $registry->get('my-ability');
        $this->assertSame($replacement, $retrieved);
        $this->assertNotSame($original, $retrieved);
    }

    public function testRegisterOverwriteReplacesAllProperties(): void
    {
        $registry = new AbilityRegistry();

        // Register initial ability
        $original = new AbilityDefinition(
            name: 'test-ability',
            description: 'Old description',
            parameterSchema: ['old' => 'schema'],
            responseSchema: ['old' => 'response'],
            handler: fn(mixed $params): string => 'old'
        );

        $registry->register($original);

        // Register replacement with same name but different properties
        $replacement = new AbilityDefinition(
            name: 'test-ability',
            description: 'New description',
            parameterSchema: ['new' => 'schema'],
            responseSchema: ['new' => 'response'],
            handler: fn(mixed $params): string => 'new'
        );

        $registry->register($replacement);

        $retrieved = $registry->get('test-ability');

        // Verify all properties come from the replacement
        $this->assertSame('New description', $retrieved->description);
        $this->assertSame(['new' => 'schema'], $retrieved->parameterSchema);
        $this->assertSame(['new' => 'response'], $retrieved->responseSchema);
    }

    public function testRegisterOverwriteDoesNotAffectOtherAbilities(): void
    {
        $registry = new AbilityRegistry();

        // Register multiple abilities
        $ability1 = new AbilityDefinition(
            name: 'ability-one',
            description: 'First',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): string => 'one'
        );

        $ability2 = new AbilityDefinition(
            name: 'ability-two',
            description: 'Second',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): string => 'two'
        );

        $registry->register($ability1);
        $registry->register($ability2);

        // Overwrite ability-one
        $newAbility1 = new AbilityDefinition(
            name: 'ability-one',
            description: 'Updated first',
            parameterSchema: [],
            responseSchema: [],
            handler: fn(mixed $params): string => 'new-one'
        );

        $registry->register($newAbility1);

        // Verify ability-one was overwritten
        $this->assertSame($newAbility1, $registry->get('ability-one'));

        // Verify ability-two was not affected
        $this->assertSame($ability2, $registry->get('ability-two'));
    }
}
