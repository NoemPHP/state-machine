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
 * Acceptance Criterion: Multiple abilities invoked in single callback all receive responses
 *
 * Intent: Confirms parallel or sequential ability invocations don't interfere with correlation
 *
 * @see specs/features/abilities.yaml - integration
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('chained')]
class ChainedInvocationsTest extends RegionBuilderTestCase
{
    #[Test]
    public function multipleThenCallbacksExecuteInOrder(): void
    {
        // Given: Ability with multiple then() callbacks
        $executionOrder = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$executionOrder) {
                // Register ability
                $this->abilities()->register('chain-test', [
                    'name' => 'chain-test',
                    'description' => 'Test chained callbacks',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['value' => 42]
                ]);

                // Chain multiple then() callbacks
                $message = $this->abilities('chain-test');

                $message->then(function ($response) use (&$executionOrder) {
                    $executionOrder[] = 'first';
                });

                $message->then(function ($response) use (&$executionOrder) {
                    $executionOrder[] = 'second';
                });

                $message->then(function ($response) use (&$executionOrder) {
                    $executionOrder[] = 'third';
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All callbacks should execute in registration order
        $this->assertCount(3, $executionOrder);
        $this->assertSame('first', $executionOrder[0]);
        $this->assertSame('second', $executionOrder[1]);
        $this->assertSame('third', $executionOrder[2]);
    }

    #[Test]
    public function chainedCallbacksReceiveSameResponse(): void
    {
        // Given: Multiple then() callbacks capturing response
        $responses = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$responses) {
                // Register ability
                $this->abilities()->register('shared-response', [
                    'name' => 'shared-response',
                    'description' => 'Tests response sharing',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['data' => 'unique-value', 'timestamp' => time()]
                ]);

                // Multiple callbacks on same message
                $message = $this->abilities('shared-response');

                $message->then(function ($response) use (&$responses) {
                    $responses['callback1'] = $response;
                });

                $message->then(function ($response) use (&$responses) {
                    $responses['callback2'] = $response;
                });

                $message->then(function ($response) use (&$responses) {
                    $responses['callback3'] = $response;
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All callbacks should receive identical response
        $this->assertCount(3, $responses);
        $this->assertSame(
            $responses['callback1'],
            $responses['callback2'],
            'Callbacks should receive same response object'
        );
        $this->assertSame(
            $responses['callback1'],
            $responses['callback3'],
            'Callbacks should receive same response object'
        );
    }

    #[Test]
    public function chainedInvocationsWithDifferentAbilities(): void
    {
        // Given: Sequential ability invocations with chained callbacks
        $results = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$results) {
                // Register multiple abilities
                $this->abilities()->register('first', [
                    'name' => 'first',
                    'description' => 'First ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['step' => 1, 'value' => 10]
                ]);

                $this->abilities()->register('second', [
                    'name' => 'second',
                    'description' => 'Second ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['step' => 2, 'value' => 20]
                ]);

                $this->abilities()->register('third', [
                    'name' => 'third',
                    'description' => 'Third ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['step' => 3, 'value' => 30]
                ]);

                // Chain invocations
                $this->abilities('first')
                    ->then(function ($response) use (&$results) {
                        $results['first'] = $response;
                    });

                $this->abilities('second')
                    ->then(function ($response) use (&$results) {
                        $results['second'] = $response;
                    });

                $this->abilities('third')
                    ->then(function ($response) use (&$results) {
                        $results['third'] = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Each invocation should receive its own response
        $this->assertCount(3, $results);
        $this->assertSame(1, $results['first']->parameters['step']);
        $this->assertSame(10, $results['first']->parameters['value']);
        $this->assertSame(2, $results['second']->parameters['step']);
        $this->assertSame(20, $results['second']->parameters['value']);
        $this->assertSame(3, $results['third']->parameters['step']);
        $this->assertSame(30, $results['third']->parameters['value']);
    }

    #[Test]
    public function nestedAbilityInvocationInCallback(): void
    {
        // Given: Ability callback that invokes another ability
        $firstResponse = null;
        $nestedResponse = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$firstResponse, &$nestedResponse) {
                // Register abilities
                $this->abilities()->register('outer', [
                    'name' => 'outer',
                    'description' => 'Outer ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['outer' => true]
                ]);

                $this->abilities()->register('inner', [
                    'name' => 'inner',
                    'description' => 'Inner ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['inner' => true]
                ]);

                // Invoke outer, then invoke inner in callback
                $this->abilities('outer')
                    ->then(function ($response) use (&$firstResponse, &$nestedResponse) {
                        $firstResponse = $response;

                        // Nested invocation inside then() callback
                        $this->abilities('inner')
                            ->then(function ($innerResp) use (&$nestedResponse) {
                                $nestedResponse = $innerResp;
                            });
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Both outer and nested inner should complete
        $this->assertNotNull($firstResponse);
        $this->assertTrue($firstResponse->parameters['outer']);

        $this->assertNotNull($nestedResponse);
        $this->assertTrue($nestedResponse->parameters['inner']);
    }

    #[Test]
    public function transformResponseInCallbackChain(): void
    {
        // Given: Chained callbacks that transform response
        $transformations = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$transformations) {
                // Register ability
                $this->abilities()->register('transform', [
                    'name' => 'transform',
                    'description' => 'Data to transform',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['value' => 1]
                ]);

                $message = $this->abilities('transform');

                // Chain transformations
                $message->then(function ($response) use (&$transformations) {
                    $transformations['original'] = $response->parameters['value'];
                });

                $message->then(function ($response) use (&$transformations) {
                    $transformations['doubled'] = $response->parameters['value'] * 2;
                });

                $message->then(function ($response) use (&$transformations) {
                    $transformations['stringified'] = "Value: {$response->parameters['value']}";
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Each callback can transform the response independently
        $this->assertSame(1, $transformations['original']);
        $this->assertSame(2, $transformations['doubled']);
        $this->assertSame('Value: 1', $transformations['stringified']);
    }
}
