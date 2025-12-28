<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: enumerate-abilities returns empty array when no custom abilities
 *
 * Intent: Validates enumeration behavior with only built-in abilities
 *
 * @see specs/features/abilities.yaml - integration (derived from spec analysis)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('enumerate')]
class EmptyRegistryTest extends RegionBuilderTestCase
{
    #[Test]
    public function enumerateWithNoCustomAbilities(): void
    {
        // Given: Region with no custom abilities registered
        $enumeration = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$enumeration) {
                // No custom abilities registered - only enumerate-abilities should exist

                // Invoke enumerate-abilities
                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$enumeration) {
                        $enumeration = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should return only built-in enumerate-abilities
        $this->assertNotNull($enumeration);
        $this->assertIsArray($enumeration->parameters);
        $this->assertArrayHasKey('abilities', $enumeration->parameters);

        $abilities = $enumeration->parameters['abilities'];
        $this->assertIsArray($abilities);

        // Should have at least enumerate-abilities itself
        $this->assertNotEmpty($abilities);

        $abilityNames = array_column($abilities, 'name');
        $this->assertContains('enumerate-abilities', $abilityNames);

        // Should only have enumerate-abilities (no custom ones)
        $this->assertCount(
            1,
            $abilities,
            'Should only contain enumerate-abilities when no custom abilities registered'
        );
    }

    #[Test]
    public function emptyRegistryAfterRegionCreation(): void
    {
        // Given: Fresh region with no abilities
        $initialEnumeration = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$initialEnumeration) {
                // Enumerate immediately without registering anything
                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$initialEnumeration) {
                        $initialEnumeration = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Registry should start with only built-in abilities
        $this->assertNotNull($initialEnumeration);
        $abilities = $initialEnumeration->parameters['abilities'];

        // Verify it's not completely empty
        $this->assertNotEmpty($abilities, 'Should have at least enumerate-abilities');

        // But should not have any user-defined abilities
        $abilityNames = array_column($abilities, 'name');
        foreach ($abilityNames as $name) {
            $this->assertStringStartsWith(
                'enumerate',
                $name,
                'Only built-in abilities should be present'
            );
        }
    }
}
