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
 * Acceptance Criterion: State callback invokes ability via $this->abilities() and receives response
 *
 * Intent: Demonstrates complete request-response cycle from invocation to then() handler execution
 *
 * @see specs/features/abilities.yaml - integration
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
class FullWorkflowTest extends RegionBuilderTestCase
{
    #[Test]
    public function completeWorkflowFromRegistrationToResponse(): void
    {
        // Given: A region with AbilitiesFeature
        $responseData = null;
        $handlerExecuted = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle', 'processing')
            ->onEnter('idle', function (object $t) use (&$responseData, &$handlerExecuted) {
                // Step 1: Register an ability
                $this->abilities()->register('calculate-sum', [
                    'name' => 'calculate-sum',
                    'description' => 'Calculates the sum of two numbers',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'a' => ['type' => 'number'],
                            'b' => ['type' => 'number'],
                        ],
                        'required' => ['a', 'b'],
                    ],
                    'responseSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'result' => ['type' => 'number'],
                        ],
                    ],
                    'handler' => function ($params) use (&$handlerExecuted) {
                        $handlerExecuted = true;
                        return ['result' => $params['a'] + $params['b']];
                    }
                ]);

                // Step 2: Invoke the ability via $this->abilities()
                $message = $this->abilities('calculate-sum', ['a' => 10, 'b' => 32]);

                // Step 3: Register then() callback to receive response
                $message->then(function ($response) use (&$responseData) {
                    $responseData = $response;
                });
            })
            ->build();

        // When: Trigger the region to execute the workflow
        $region->trigger((object)[]);

        // Then: Complete workflow should have executed
        $this->assertTrue(
            $handlerExecuted,
            'Ability handler should have executed'
        );

        $this->assertNotNull(
            $responseData,
            'Response should have been delivered to then() callback'
        );

        $this->assertIsArray($responseData->parameters);
        $this->assertArrayHasKey('result', $responseData->parameters);
        $this->assertSame(
            42,
            $responseData->parameters['result'],
            'Response should contain correct calculation result'
        );
    }

    #[Test]
    public function multiStepWorkflowWithDifferentAbilities(): void
    {
        // Given: Multiple abilities registered in workflow
        $greetResponse = null;
        $mathResponse = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$greetResponse, &$mathResponse) {
                // Register greeting ability
                $this->abilities()->register('greet', [
                    'name' => 'greet',
                    'description' => 'Returns a greeting',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                        'required' => ['name'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['message' => "Hello, {$params['name']}!"]
                ]);

                // Register math ability
                $this->abilities()->register('multiply', [
                    'name' => 'multiply',
                    'description' => 'Multiplies two numbers',
                    'parameterSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'x' => ['type' => 'number'],
                            'y' => ['type' => 'number'],
                        ],
                        'required' => ['x', 'y'],
                    ],
                    'responseSchema' => [],
                    'handler' => fn($params) => ['product' => $params['x'] * $params['y']]
                ]);

                // Invoke both abilities
                $this->abilities('greet', ['name' => 'Alice'])
                    ->then(function ($response) use (&$greetResponse) {
                        $greetResponse = $response;
                    });

                $this->abilities('multiply', ['x' => 6, 'y' => 7])
                    ->then(function ($response) use (&$mathResponse) {
                        $mathResponse = $response;
                    });
            })
            ->build();

        // When: Trigger workflow
        $region->trigger((object)[]);

        // Then: Both responses should be delivered
        $this->assertNotNull($greetResponse);
        $this->assertSame('Hello, Alice!', $greetResponse->parameters['message']);

        $this->assertNotNull($mathResponse);
        $this->assertSame(42, $mathResponse->parameters['product']);
    }

    #[Test]
    public function workflowWithParameterlessAbility(): void
    {
        // Given: Ability without required parameters
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response) {
                // Register parameterless ability
                $this->abilities()->register('get-timestamp', [
                    'name' => 'get-timestamp',
                    'description' => 'Returns current timestamp',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['timestamp' => time()]
                ]);

                // Invoke without parameters
                $this->abilities('get-timestamp')
                    ->then(function ($data) use (&$response) {
                        $response = $data;
                    });
            })
            ->build();

        // When: Trigger workflow
        $region->trigger((object)[]);

        // Then: Response should be delivered
        $this->assertNotNull($response);
        $this->assertArrayHasKey('timestamp', $response->parameters);
        $this->assertIsInt($response->parameters['timestamp']);
    }
}
