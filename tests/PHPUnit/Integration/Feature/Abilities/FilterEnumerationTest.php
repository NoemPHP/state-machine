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
 * Acceptance Criterion: enumerate-abilities filter parameter works correctly
 *
 * Intent: Validates regex filtering of ability enumeration
 *
 * @see specs/features/abilities.yaml - integration (derived from spec analysis)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('enumerate')]
class FilterEnumerationTest extends RegionBuilderTestCase
{
    #[Test]
    public function filterByRegexPattern(): void
    {
        // Given: Multiple abilities with different name patterns
        $filteredResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$filteredResult) {
                // Register abilities with different prefixes
                $this->abilities()->register('user-create', [
                    'name' => 'user-create',
                    'description' => 'Create user',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                $this->abilities()->register('user-update', [
                    'name' => 'user-update',
                    'description' => 'Update user',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                $this->abilities()->register('post-create', [
                    'name' => 'post-create',
                    'description' => 'Create post',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                $this->abilities()->register('post-delete', [
                    'name' => 'post-delete',
                    'description' => 'Delete post',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Enumerate with filter for 'user-*' abilities
                $this->abilities('enumerate-abilities', ['filter' => '^user-'])
                    ->then(function ($response) use (&$filteredResult) {
                        $filteredResult = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should only return user-* abilities
        $this->assertNotNull($filteredResult);
        $abilities = $filteredResult->parameters['abilities'];
        $abilityNames = array_column($abilities, 'name');

        $this->assertContains('user-create', $abilityNames);
        $this->assertContains('user-update', $abilityNames);
        $this->assertNotContains('post-create', $abilityNames);
        $this->assertNotContains('post-delete', $abilityNames);
        $this->assertNotContains('enumerate-abilities', $abilityNames);
    }

    #[Test]
    public function filterReturnsEmptyForNoMatches(): void
    {
        // Given: Abilities that don't match filter
        $filteredResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$filteredResult) {
                // Register abilities
                $this->abilities()->register('test-one', [
                    'name' => 'test-one',
                    'description' => 'Test one',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Filter for non-existent pattern
                $this->abilities('enumerate-abilities', ['filter' => '^nonexistent-'])
                    ->then(function ($response) use (&$filteredResult) {
                        $filteredResult = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should return empty array
        $this->assertNotNull($filteredResult);
        $this->assertArrayHasKey('abilities', $filteredResult->parameters);
        $this->assertEmpty($filteredResult->parameters['abilities']);
    }

    #[Test]
    public function noFilterReturnsAllAbilities(): void
    {
        // Given: Multiple abilities without filter
        $unfilteredResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$unfilteredResult) {
                // Register various abilities
                $this->abilities()->register('ability-a', [
                    'name' => 'ability-a',
                    'description' => 'Ability A',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                $this->abilities()->register('ability-b', [
                    'name' => 'ability-b',
                    'description' => 'Ability B',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                $this->abilities()->register('ability-c', [
                    'name' => 'ability-c',
                    'description' => 'Ability C',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Enumerate without filter
                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$unfilteredResult) {
                        $unfilteredResult = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should return all abilities
        $this->assertNotNull($unfilteredResult);
        $abilities = $unfilteredResult->parameters['abilities'];
        $abilityNames = array_column($abilities, 'name');

        $this->assertContains('ability-a', $abilityNames);
        $this->assertContains('ability-b', $abilityNames);
        $this->assertContains('ability-c', $abilityNames);
        $this->assertContains('enumerate-abilities', $abilityNames);
    }

    #[Test]
    public function complexRegexFilter(): void
    {
        // Given: Complex regex pattern
        $filteredResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$filteredResult) {
                // Register abilities with various patterns
                $this->abilities()->register('get-user', [
                    'name' => 'get-user',
                    'description' => 'Get user',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                $this->abilities()->register('get-post', [
                    'name' => 'get-post',
                    'description' => 'Get post',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                $this->abilities()->register('create-user', [
                    'name' => 'create-user',
                    'description' => 'Create user',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Filter for abilities starting with 'get-'
                $this->abilities('enumerate-abilities', ['filter' => '^get-'])
                    ->then(function ($response) use (&$filteredResult) {
                        $filteredResult = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should match regex pattern
        $this->assertNotNull($filteredResult);
        $abilityNames = array_column($filteredResult->parameters['abilities'], 'name');

        $this->assertContains('get-user', $abilityNames);
        $this->assertContains('get-post', $abilityNames);
        $this->assertNotContains('create-user', $abilityNames);
    }
}
