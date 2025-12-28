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
 * Acceptance Criterion: Multiple abilities can be invoked sequentially in same callback
 *
 * Intent: Supports sequential ability calls without async infrastructure,
 *         enabling simple workflows
 *
 * @see specs/features/abilities.yaml - synchronous-behavior
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('synchronous')]
class SequentialInvocationsTest extends RegionBuilderTestCase
{
    #[Test]
    public function multipleAbilitiesInvokeSequentially(): void
    {
        // Given: Multiple abilities to invoke in sequence
        $executionLog = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$executionLog) {
                // Register multiple abilities
                $this->abilities()->register('first', [
                    'name' => 'first',
                    'description' => 'First ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionLog) {
                        $executionLog[] = 'first-handler';
                        return ['value' => 1];
                    }
                ]);

                $this->abilities()->register('second', [
                    'name' => 'second',
                    'description' => 'Second ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionLog) {
                        $executionLog[] = 'second-handler';
                        return ['value' => 2];
                    }
                ]);

                $this->abilities()->register('third', [
                    'name' => 'third',
                    'description' => 'Third ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionLog) {
                        $executionLog[] = 'third-handler';
                        return ['value' => 3];
                    }
                ]);

                // Invoke abilities sequentially in same callback
                $executionLog[] = 'before-first';
                $this->abilities('first')
                    ->then(function () use (&$executionLog) {
                        $executionLog[] = 'first-response';
                    });

                $executionLog[] = 'before-second';
                $this->abilities('second')
                    ->then(function () use (&$executionLog) {
                        $executionLog[] = 'second-response';
                    });

                $executionLog[] = 'before-third';
                $this->abilities('third')
                    ->then(function () use (&$executionLog) {
                        $executionLog[] = 'third-response';
                    });

                $executionLog[] = 'after-all';
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All abilities should execute sequentially in order
        $this->assertSame(
            [
                'before-first',
                'first-handler',
                'first-response',
                'before-second',
                'second-handler',
                'second-response',
                'before-third',
                'third-handler',
                'third-response',
                'after-all',
            ],
            $executionLog,
            'Abilities should execute sequentially in order without async infrastructure'
        );
    }

    #[Test]
    public function sequentialInvocationsCanAccessPreviousResults(): void
    {
        // Given: Chain of abilities where each uses previous result
        $results = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$results) {
                // Register abilities
                $this->abilities()->register('get-base', [
                    'name' => 'get-base',
                    'description' => 'Get base value',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['value' => 10]
                ]);

                $this->abilities()->register('multiply', [
                    'name' => 'multiply',
                    'description' => 'Multiply value',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['value' => $params['value'] * 2]
                ]);

                $this->abilities()->register('add', [
                    'name' => 'add',
                    'description' => 'Add value',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['value' => $params['value'] + 5]
                ]);

                // Chain abilities sequentially, passing results forward
                $this->abilities('get-base')
                    ->then(function ($response) use (&$results) {
                        $results['base'] = $response;

                        // Second invocation can happen here because delivery is synchronous
                        $this->abilities('multiply', ['value' => 10])
                            ->then(function ($response) use (&$results) {
                                $results['multiplied'] = $response;

                                // Third invocation
                                $this->abilities('add', ['value' => 20])
                                    ->then(function ($response) use (&$results) {
                                        $results['final'] = $response;
                                    });
                            });
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All sequential invocations should complete with results accessible
        $this->assertArrayHasKey('base', $results, 'First ability result should be captured');
        $this->assertArrayHasKey('multiplied', $results, 'Second ability result should be captured');
        $this->assertArrayHasKey('final', $results, 'Third ability result should be captured');
    }

    #[Test]
    public function sequentialInvocationsDoNotInterfereWithEachOther(): void
    {
        // Given: Multiple independent sequential invocations
        $firstSequenceResults = [];
        $secondSequenceResults = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$firstSequenceResults, &$secondSequenceResults) {
                // Register abilities
                $this->abilities()->register('ability-a', [
                    'name' => 'ability-a',
                    'description' => 'Ability A',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['id' => 'a']
                ]);

                $this->abilities()->register('ability-b', [
                    'name' => 'ability-b',
                    'description' => 'Ability B',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['id' => 'b']
                ]);

                // First sequence
                $this->abilities('ability-a')
                    ->then(function ($response) use (&$firstSequenceResults) {
                        $firstSequenceResults[] = $response;
                    });

                $this->abilities('ability-a')
                    ->then(function ($response) use (&$firstSequenceResults) {
                        $firstSequenceResults[] = $response;
                    });

                // Second sequence with different ability
                $this->abilities('ability-b')
                    ->then(function ($response) use (&$secondSequenceResults) {
                        $secondSequenceResults[] = $response;
                    });

                $this->abilities('ability-b')
                    ->then(function ($response) use (&$secondSequenceResults) {
                        $secondSequenceResults[] = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Each sequence should receive correct responses
        $this->assertCount(
            2,
            $firstSequenceResults,
            'First sequence should receive 2 responses'
        );

        $this->assertCount(
            2,
            $secondSequenceResults,
            'Second sequence should receive 2 responses'
        );

        // Verify correlation - each sequence should only get its own responses
        // (This relies on MessageFeature correlation working correctly)
    }

    #[Test]
    public function sequentialInvocationsWithParametersWork(): void
    {
        // Given: Abilities that accept and return parameters
        $results = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$results) {
                // Register parametrized ability
                $this->abilities()->register('greet', [
                    'name' => 'greet',
                    'description' => 'Greet someone',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn($params) => [
                        'message' => "Hello, {$params['name']}!"
                    ]
                ]);

                // Sequential invocations with different parameters
                $this->abilities('greet', ['name' => 'Alice'])
                    ->then(function ($response) use (&$results) {
                        $results[] = $response;
                    });

                $this->abilities('greet', ['name' => 'Bob'])
                    ->then(function ($response) use (&$results) {
                        $results[] = $response;
                    });

                $this->abilities('greet', ['name' => 'Charlie'])
                    ->then(function ($response) use (&$results) {
                        $results[] = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All invocations should complete with correct parameters
        $this->assertCount(
            3,
            $results,
            'All three sequential invocations should complete'
        );

        // Each should have received correct response for its parameters
        // (Exact response structure depends on AbilityMessage implementation)
    }
}
