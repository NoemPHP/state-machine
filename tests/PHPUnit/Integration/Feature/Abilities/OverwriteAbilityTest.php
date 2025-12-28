<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Re-registering ability with same name replaces previous
 *
 * Intent: Validates ability redefinition with explicit overwrite semantics
 *
 * @see specs/features/abilities.yaml - integration (derived from spec analysis)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
class OverwriteAbilityTest extends RegionBuilderTestCase
{
    #[Test]
    public function reRegistrationOverwritesPrevious(): void
    {
        // Given: Ability registered then re-registered with same name
        $firstResponse = null;
        $secondResponse = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new \Noem\State\Feature\Transitions\TransitionsFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle', 'active')
            ->addBuildStep(new AddTransition('idle', 'active'))
            ->onEnter('idle', function (object $t) use (&$firstResponse) {
                // Register initial ability
                $this->abilities()->register('overwrite-test', [
                    'name' => 'overwrite-test',
                    'description' => 'Original version',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['version' => 1]
                ]);

                // Invoke first version
                $this->abilities('overwrite-test')
                    ->then(function ($response) use (&$firstResponse) {
                        $firstResponse = $response;
                    });
            })
            ->onEnter('active', function (object $t) use (&$secondResponse) {
                // Re-register with same name
                $this->abilities()->register('overwrite-test', [
                    'name' => 'overwrite-test',
                    'description' => 'Updated version',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['version' => 2]
                ]);

                // Invoke second version
                $this->abilities('overwrite-test')
                    ->then(function ($response) use (&$secondResponse) {
                        $secondResponse = $response;
                    });
            })
            ->build();

        // When: Trigger both states
        $region->trigger((object)[]);
        $region->trigger((object)['type' => 'transition']);

        // Then: Second registration should replace first
        $this->assertNotNull($firstResponse);
        $this->assertSame(1, $firstResponse->parameters['version']);

        $this->assertNotNull($secondResponse);
        $this->assertSame(2, $secondResponse->parameters['version']);
    }

    #[Test]
    public function overwrittenAbilityNotInEnumeration(): void
    {
        // Given: Ability overwritten then enumerated
        $enumeration = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$enumeration) {
                // Register initial
                $this->abilities()->register('test', [
                    'name' => 'test',
                    'description' => 'Original',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Overwrite
                $this->abilities()->register('test', [
                    'name' => 'test',
                    'description' => 'Overwritten',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Enumerate
                $this->abilities('enumerate-abilities')
                    ->then(function ($response) use (&$enumeration) {
                        $enumeration = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should only have one entry for 'test' with new description
        $this->assertNotNull($enumeration);
        $abilities = $enumeration->parameters['abilities'];

        $testAbilities = array_filter($abilities, fn($a) => $a['name'] === 'test');
        $this->assertCount(1, $testAbilities, 'Should only have one entry for overwritten ability');

        $testAbility = array_values($testAbilities)[0];
        $this->assertSame('Overwritten', $testAbility['description']);
    }

    #[Test]
    public function overwriteWithDifferentSchema(): void
    {
        // Given: Ability overwritten with different schema
        $validationError = null;
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$validationError, &$response) {
                // Register with no required parameters
                $this->abilities()->register('flexible', [
                    'name' => 'flexible',
                    'description' => 'No validation',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn($params) => $params
                ]);

                // Overwrite with strict schema
                $this->abilities()->register('flexible', [
                    'name' => 'flexible',
                    'description' => 'Strict validation',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'required_field' => ['type' => 'string'],
                        ],
                        'required' => ['required_field'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => $params
                ]);

                // Try to invoke without required field (should fail)
                try {
                    $this->abilities('flexible', []);
                } catch (\Throwable $e) {
                    $validationError = $e;
                }

                // Invoke with required field (should succeed)
                $this->abilities('flexible', ['required_field' => 'value'])
                    ->then(function ($data) use (&$response) {
                        $response = $data;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: New schema should be enforced
        $this->assertNotNull($validationError);
        $this->assertInstanceOf(
            \Noem\State\Feature\Abilities\SchemaValidationException::class,
            $validationError
        );

        $this->assertNotNull($response);
        $this->assertSame('value', $response->parameters['required_field']);
    }
}
