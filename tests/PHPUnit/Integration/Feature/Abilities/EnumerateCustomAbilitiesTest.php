<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Enumerate-abilities returns list including custom registered abilities
 *
 * Intent: Validates meta-reflexive enumeration includes both built-in and user-defined abilities
 *
 * @see specs/features/abilities.yaml - integration
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('enumerate')]
class EnumerateCustomAbilitiesTest extends RegionBuilderTestCase
{
    #[Test]
    public function enumerateIncludesCustomRegisteredAbilities(): void
    {
        // Given: Region with custom abilities registered
        $enumeration = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$enumeration) {
                // Register custom abilities
                $this->abilities()->register('custom-one', [
                    'name' => 'custom-one',
                    'description' => 'First custom ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['ok' => true]
                ]);

                $this->abilities()->register('custom-two', [
                    'name' => 'custom-two',
                    'description' => 'Second custom ability',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'value' => ['type' => 'string'],
                        ],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => $params
                ]);

                // Invoke enumerate-abilities
                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$enumeration) {
                        $enumeration = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Enumeration should include both built-in and custom abilities
        $this->assertNotNull($enumeration, 'Enumeration response should be delivered');
        $this->assertIsArray($enumeration->parameters);
        $this->assertArrayHasKey('abilities', $enumeration->parameters);

        $abilities = $enumeration->parameters['abilities'];
        $this->assertIsArray($abilities);

        // Should include enumerate-abilities itself (meta-reflexive)
        $abilityNames = array_column($abilities, 'name');
        $this->assertContains('enumerate-abilities', $abilityNames);

        // Should include custom abilities
        $this->assertContains('custom-one', $abilityNames);
        $this->assertContains('custom-two', $abilityNames);

        // Verify custom ability details are present
        $customOne = null;
        $customTwo = null;
        foreach ($abilities as $ability) {
            if ($ability['name'] === 'custom-one') {
                $customOne = $ability;
            }
            if ($ability['name'] === 'custom-two') {
                $customTwo = $ability;
            }
        }

        $this->assertNotNull($customOne);
        $this->assertSame('First custom ability', $customOne['description']);
        $this->assertArrayHasKey('parameterSchema', $customOne);
        $this->assertArrayHasKey('responseSchema', $customOne);

        $this->assertNotNull($customTwo);
        $this->assertSame('Second custom ability', $customTwo['description']);
        $this->assertArrayHasKey('parameterSchema', $customTwo);
        $this->assertArrayNotHasKey('handler', $customTwo, 'Handler should not be exposed in enumeration');
    }

    #[Test]
    public function enumerationIncludesSchemaInformation(): void
    {
        // Given: Ability with detailed schema
        $enumeration = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$enumeration) {
                // Register ability with complex schema
                $this->abilities()->register('process-data', [
                    'name' => 'process-data',
                    'description' => 'Processes input data',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'items' => ['type' => 'string'],
                            ],
                            'count' => ['type' => 'integer'],
                        ],
                        'required' => ['items'],
                    ],
                    'responseSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'processed' => ['type' => 'boolean'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                    'handler' => fn($params) => ['processed' => true, 'total' => count($params['items'])]
                ]);

                // Enumerate abilities
                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$enumeration) {
                        $enumeration = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Schema information should be included
        $this->assertNotNull($enumeration);
        $abilities = $enumeration->parameters['abilities'];

        $processData = null;
        foreach ($abilities as $ability) {
            if ($ability['name'] === 'process-data') {
                $processData = $ability;
                break;
            }
        }

        $this->assertNotNull($processData);
        $this->assertArrayHasKey('parameterSchema', $processData);
        $this->assertArrayHasKey('responseSchema', $processData);

        // Verify schema structure is preserved
        $paramSchema = $processData['parameterSchema'];
        $this->assertSame('object', $paramSchema['type']);
        $this->assertArrayHasKey('properties', $paramSchema);
        $this->assertArrayHasKey('items', $paramSchema['properties']);
        $this->assertArrayHasKey('required', $paramSchema);
        $this->assertContains('items', $paramSchema['required']);
    }

    #[Test]
    public function enumerationReflectsCurrentRegistryState(): void
    {
        // Given: Region where abilities are registered over time
        $firstEnumeration = null;
        $secondEnumeration = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new TransitionsFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle', 'active')
            ->addBuildStep(new AddTransition('idle', 'active'))
            ->onEnter('idle', function (object $t) use (&$firstEnumeration) {
                // Register first ability
                $this->abilities()->register('ability-one', [
                    'name' => 'ability-one',
                    'description' => 'First ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Enumerate - should only see ability-one + enumerate-abilities
                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$firstEnumeration) {
                        $firstEnumeration = $response;
                    });
            })
            ->onEnter('active', function (object $t) use (&$secondEnumeration) {
                // Register second ability
                $this->abilities()->register('ability-two', [
                    'name' => 'ability-two',
                    'description' => 'Second ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Enumerate again - should see both abilities
                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$secondEnumeration) {
                        $secondEnumeration = $response;
                    });
            })
            ->build();

        // When: Trigger to idle, then transition to active
        $region->trigger((object)[]);
        $region->trigger((object)['type' => 'transition']); // Transition to active

        // Then: Enumerations should reflect registry state at each point
        $this->assertNotNull($firstEnumeration);
        $this->assertNotNull($secondEnumeration);

        $firstNames = array_column($firstEnumeration->parameters['abilities'], 'name');
        $secondNames = array_column($secondEnumeration->parameters['abilities'], 'name');

        // First enumeration has only ability-one
        $this->assertContains('ability-one', $firstNames);
        $this->assertNotContains('ability-two', $firstNames);

        // Second enumeration has both
        $this->assertContains('ability-one', $secondNames);
        $this->assertContains('ability-two', $secondNames);
    }
}
