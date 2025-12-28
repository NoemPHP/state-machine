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
 * Acceptance Criterion: Ability with complex nested parameter schema validates correctly
 *
 * Intent: Validates schema validation handles deeply nested objects and arrays
 *
 * @see specs/features/abilities.yaml - integration
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('schema')]
class ComplexSchemaTest extends RegionBuilderTestCase
{
    #[Test]
    public function nestedObjectSchemaValidates(): void
    {
        // Given: Ability with nested object schema
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response) {
                // Register ability with nested schema
                $this->abilities()->register('nested-object', [
                    'name' => 'nested-object',
                    'description' => 'Nested object validation',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'user' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string'],
                                    'profile' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'age' => ['type' => 'integer'],
                                            'email' => ['type' => 'string'],
                                        ],
                                        'required' => ['age'],
                                    ],
                                ],
                                'required' => ['name', 'profile'],
                            ],
                        ],
                        'required' => ['user'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => $params
                ]);

                // Invoke with valid nested structure
                $this->abilities('nested-object', [
                    'user' => [
                        'name' => 'Alice',
                        'profile' => [
                            'age' => 30,
                            'email' => 'alice@example.com',
                        ],
                    ],
                ])->then(function ($data) use (&$response) {
                    $response = $data;
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Nested structure should validate and execute
        $this->assertNotNull($response);
        $this->assertArrayHasKey('user', $response->parameters);
        $this->assertSame('Alice', $response->parameters['user']['name']);
        $this->assertSame(30, $response->parameters['user']['profile']['age']);
    }

    #[Test]
    public function arrayOfObjectsSchemaValidates(): void
    {
        // Given: Ability with array of objects schema
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response) {
                // Register ability
                $this->abilities()->register('array-of-objects', [
                    'name' => 'array-of-objects',
                    'description' => 'Array validation',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'items' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'integer'],
                                        'label' => ['type' => 'string'],
                                    ],
                                    'required' => ['id', 'label'],
                                ],
                            ],
                        ],
                        'required' => ['items'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['count' => count($params['items'])]
                ]);

                // Invoke with array of objects
                $this->abilities('array-of-objects', [
                    'items' => [
                        ['id' => 1, 'label' => 'First'],
                        ['id' => 2, 'label' => 'Second'],
                        ['id' => 3, 'label' => 'Third'],
                    ],
                ])->then(function ($data) use (&$response) {
                    $response = $data;
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Array of objects should validate
        $this->assertNotNull($response);
        $this->assertSame(3, $response->parameters['count']);
    }

    #[Test]
    public function deeplyNestedSchemaRejectsInvalid(): void
    {
        // Given: Deeply nested schema
        $exceptionCaught = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$exceptionCaught) {
                // Register ability with deep nesting
                $this->abilities()->register('deep-nested', [
                    'name' => 'deep-nested',
                    'description' => 'Deep nesting test',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'level1' => [
                                'type' => 'object',
                                'properties' => [
                                    'level2' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'level3' => [
                                                'type' => 'object',
                                                'properties' => [
                                                    'value' => ['type' => 'integer'],
                                                ],
                                                'required' => ['value'],
                                            ],
                                        ],
                                        'required' => ['level3'],
                                    ],
                                ],
                                'required' => ['level2'],
                            ],
                        ],
                        'required' => ['level1'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => []
                ]);

                // Try with invalid deep value (string instead of integer)
                try {
                    $this->abilities('deep-nested', [
                        'level1' => [
                            'level2' => [
                                'level3' => [
                                    'value' => 'not-an-integer',
                                ],
                            ],
                        ],
                    ]);
                } catch (\Throwable $e) {
                    $exceptionCaught = $e;
                }
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Should reject invalid nested value
        $this->assertNotNull($exceptionCaught);
        $this->assertInstanceOf(
            \Noem\State\Feature\Abilities\Exception\SchemaValidationException::class,
            $exceptionCaught
        );
    }

    #[Test]
    public function mixedNestedArraysAndObjects(): void
    {
        // Given: Complex schema mixing arrays and objects
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response) {
                // Register ability with complex schema
                $this->abilities()->register('complex-mixed', [
                    'name' => 'complex-mixed',
                    'description' => 'Complex mixed types',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'users' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'name' => ['type' => 'string'],
                                        'tags' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                        ],
                                        'metadata' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'score' => ['type' => 'number'],
                                            ],
                                        ],
                                    ],
                                    'required' => ['name'],
                                ],
                            ],
                        ],
                        'required' => ['users'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['processed' => true]
                ]);

                // Invoke with complex structure
                $this->abilities('complex-mixed', [
                    'users' => [
                        [
                            'name' => 'Alice',
                            'tags' => ['admin', 'verified'],
                            'metadata' => ['score' => 95.5],
                        ],
                        [
                            'name' => 'Bob',
                            'tags' => ['user'],
                            'metadata' => ['score' => 80.0],
                        ],
                    ],
                ])->then(function ($data) use (&$response) {
                    $response = $data;
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Complex structure should validate
        $this->assertNotNull($response);
        $this->assertTrue($response->parameters['processed']);
    }
}
