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
 * Acceptance Criterion: Ability with parameter validation rejects invalid parameters
 *
 * Intent: Confirms schema validation prevents invocation with wrong parameter types or missing required fields
 *
 * @see specs/features/abilities.yaml - integration
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('validation')]
class ParameterValidationIntegrationTest extends RegionBuilderTestCase
{
    #[Test]
    public function schemaValidationRejectsInvalidParameters(): void
    {
        // Given: Ability with strict parameter schema
        $exceptionCaught = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$exceptionCaught) {
                // Register ability with required parameters
                $this->abilities()->register('validate-me', [
                    'name' => 'validate-me',
                    'description' => 'Tests validation',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'username' => ['type' => 'string'],
                            'age' => ['type' => 'integer'],
                        ],
                        'required' => ['username', 'age'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['ok' => true]
                ]);

                // Try to invoke with invalid parameters (missing required field)
                try {
                    $this->abilities('validate-me', ['username' => 'Alice']);
                } catch (\Throwable $e) {
                    $exceptionCaught = $e;
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: SchemaValidationException should be thrown
        $this->assertNotNull(
            $exceptionCaught,
            'Should throw exception for missing required parameter'
        );

        $this->assertInstanceOf(
            \Noem\State\Feature\Abilities\Exception\SchemaValidationException::class,
            $exceptionCaught
        );

        $this->assertStringContainsString(
            'age',
            $exceptionCaught->getMessage(),
            'Error message should mention missing required field'
        );
    }

    #[Test]
    public function schemaValidationRejectsTypeMismatch(): void
    {
        // Given: Ability with type constraints
        $exceptionCaught = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$exceptionCaught) {
                // Register ability expecting integer
                $this->abilities()->register('number-test', [
                    'name' => 'number-test',
                    'description' => 'Expects number',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'count' => ['type' => 'integer'],
                        ],
                        'required' => ['count'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => []
                ]);

                // Try to invoke with string instead of integer
                try {
                    $this->abilities('number-test', ['count' => 'not-a-number']);
                } catch (\Throwable $e) {
                    $exceptionCaught = $e;
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should reject type mismatch
        $this->assertNotNull($exceptionCaught);
        $this->assertInstanceOf(
            \Noem\State\Feature\Abilities\Exception\SchemaValidationException::class,
            $exceptionCaught
        );
    }

    #[Test]
    public function schemaValidationAcceptsValidParameters(): void
    {
        // Given: Ability with parameter schema
        $handlerExecuted = false;
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlerExecuted, &$response) {
                // Register ability
                $this->abilities()->register('valid-test', [
                    'name' => 'valid-test',
                    'description' => 'Tests valid parameters',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'score' => ['type' => 'number'],
                        ],
                        'required' => ['name', 'score'],
                    ],
                    'responseSchema' => [],
                    'handler' => function ($params) use (&$handlerExecuted) {
                        $handlerExecuted = true;
                        return ['received' => $params];
                    }
                ]);

                // Invoke with valid parameters
                $this->abilities('valid-test', [
                    'name' => 'Bob',
                    'score' => 95.5,
                ])->then(function ($data) use (&$response) {
                    $response = $data;
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should accept valid parameters and execute handler
        $this->assertTrue(
            $handlerExecuted,
            'Handler should execute when parameters are valid'
        );

        $this->assertNotNull($response);
        $this->assertArrayHasKey('received', $response->parameters);
        $this->assertSame('Bob', $response->parameters['received']['name']);
        $this->assertSame(95.5, $response->parameters['received']['score']);
    }

    #[Test]
    public function validationErrorsProvidesClearMessage(): void
    {
        // Given: Multiple validation errors
        $exceptionCaught = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$exceptionCaught) {
                // Register ability with multiple constraints
                $this->abilities()->register('multi-validate', [
                    'name' => 'multi-validate',
                    'description' => 'Multiple validation rules',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'email' => [
                                'type' => 'string',
                                'format' => 'email',
                            ],
                            'age' => [
                                'type' => 'integer',
                                'minimum' => 0,
                                'maximum' => 120,
                            ],
                        ],
                        'required' => ['email', 'age'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => []
                ]);

                // Invoke with invalid parameters
                try {
                    $this->abilities('multi-validate', [
                        'email' => 'not-an-email',
                        'age' => 150, // Exceeds maximum
                    ]);
                } catch (\Throwable $e) {
                    $exceptionCaught = $e;
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Exception should provide clear error details
        $this->assertNotNull($exceptionCaught);
        $this->assertInstanceOf(
            \Noem\State\Feature\Abilities\Exception\SchemaValidationException::class,
            $exceptionCaught
        );

        // Error message should be actionable for debugging
        $message = $exceptionCaught->getMessage();
        $this->assertNotEmpty($message);
        $this->assertStringContainsString(
            'multi-validate',
            $message,
            'Should mention ability name'
        );
    }

    #[Test]
    public function optionalParametersAreAllowed(): void
    {
        // Given: Ability with optional parameters
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response) {
                // Register ability with optional parameter
                $this->abilities()->register('optional-params', [
                    'name' => 'optional-params',
                    'description' => 'Has optional parameters',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'required_field' => ['type' => 'string'],
                            'optional_field' => ['type' => 'string'],
                        ],
                        'required' => ['required_field'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => $params
                ]);

                // Invoke without optional parameter
                $this->abilities('optional-params', [
                    'required_field' => 'value',
                ])->then(function ($data) use (&$response) {
                    $response = $data;
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should accept parameters with optional field omitted
        $this->assertNotNull($response);
        $this->assertArrayHasKey('required_field', $response->parameters);
        $this->assertSame('value', $response->parameters['required_field']);
    }
}
