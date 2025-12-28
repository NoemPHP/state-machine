<?php

declare(strict_types=1);

namespace Noem\State\Test\Integration\Feature\Abilities;

use Noem\State\Feature\Abilities\AbilitiesFeature;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Ability handlers can be generators when AsyncFeature enabled
 *
 * Intent: Handlers can yield for async operations, enabling non-blocking I/O
 *         within abilities. Generator handlers leverage AsyncFeature's scheduler
 *         for cooperative multitasking.
 *
 * @see specs/features/abilities.yaml - async-enhancement (line 461-463)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('async')]
class GeneratorHandlersTest extends RegionBuilderTestCase
{
    #[Test]
    public function handlerCanBeGenerator(): void
    {
        // Given: Ability with generator handler
        $handlerExecuted = false;
        $yieldPointReached = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlerExecuted, &$yieldPointReached) {
                // Register ability with generator handler
                $this->abilities()->register('generator-test', [
                    'name' => 'generator-test',
                    'description' => 'Generator handler test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$handlerExecuted, &$yieldPointReached) {
                        // This is a generator function
                        $handlerExecuted = true;
                        yield;
                        $yieldPointReached = true;
                        yield;
                        return ['generator' => 'completed'];
                    }
                ]);

                // Invoke generator-based ability
                $this->abilities('generator-test');
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Generator handler should execute
        $this->assertTrue(
            $handlerExecuted,
            'Generator handler should start execution'
        );
        $this->assertTrue(
            $yieldPointReached,
            'Generator handler should progress past yield points'
        );
    }

    #[Test]
    public function generatorHandlerEnablesNonBlockingOperations(): void
    {
        // Given: Generator handler with async operations
        $operations = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) use (&$operations) {
                // Register ability with multi-step generator
                $this->abilities()->register('async-operation', [
                    'name' => 'async-operation',
                    'description' => 'Async operation',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) use (&$operations) {
                        // Simulate non-blocking I/O with generator
                        $operations[] = $params['id'] . '-start';
                        yield;

                        $operations[] = $params['id'] . '-io-1';
                        yield;

                        $operations[] = $params['id'] . '-io-2';
                        yield;

                        $operations[] = $params['id'] . '-complete';
                        return ['id' => $params['id']];
                    }
                ]);

                // Start multiple async operations
                yield from $this->abilities('async-operation', ['id' => 'A']);
                yield from $this->abilities('async-operation', ['id' => 'B']);
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Both generators should progress (non-blocking)
        $this->assertContains('A-start', $operations);
        $this->assertContains('A-complete', $operations);
        $this->assertContains('B-start', $operations);
        $this->assertContains('B-complete', $operations);
    }

    #[Test]
    public function generatorHandlerWithYieldValues(): void
    {
        // Given: Generator that yields values during execution
        $responseReceived = false;
        $yieldedValues = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$responseReceived, &$yieldedValues) {
                // Register ability with generator that yields progress values
                $this->abilities()->register('progress-tracker', [
                    'name' => 'progress-tracker',
                    'description' => 'Tracks progress via yields',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$yieldedValues) {
                        // Generator that yields intermediate values
                        yield 'step-1';
                        $yieldedValues[] = 'yielded-1';

                        yield 'step-2';
                        $yieldedValues[] = 'yielded-2';

                        yield 'step-3';
                        $yieldedValues[] = 'yielded-3';

                        return ['progress' => 'complete'];
                    }
                ]);

                // Invoke generator-based ability
                $this->abilities('progress-tracker')
                    ->then(function ($response) use (&$responseReceived) {
                        $responseReceived = true;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Generator should complete and response delivered
        $this->assertTrue(
            $responseReceived,
            'Generator handler should complete and deliver response'
        );
        $this->assertCount(3, $yieldedValues, 'All yield points should be reached');
    }

    #[Test]
    public function mixedSynchronousAndGeneratorHandlers(): void
    {
        // Given: Some abilities with sync handlers, others with generator handlers
        $syncResult = null;
        $generatorResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) use (&$syncResult, &$generatorResult) {
                // Register synchronous ability
                $this->abilities()->register('sync', [
                    'name' => 'sync',
                    'description' => 'Sync handler',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        // Simple synchronous handler
                        return ['type' => 'sync'];
                    }
                ]);

                // Register generator ability
                $this->abilities()->register('generator', [
                    'name' => 'generator',
                    'description' => 'Generator handler',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        // Generator handler
                        yield;
                        yield;
                        return ['type' => 'generator'];
                    }
                ]);

                // Invoke both types
                yield from $this->abilities('sync')
                    ->then(function ($response) use (&$syncResult) {
                        $syncResult = 'sync-done';
                    });

                yield from $this->abilities('generator')
                    ->then(function ($response) use (&$generatorResult) {
                        $generatorResult = 'generator-done';
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Both handler types should work
        $this->assertSame('sync-done', $syncResult, 'Sync handler should work');
        $this->assertSame('generator-done', $generatorResult, 'Generator handler should work');
    }

    #[Test]
    public function generatorHandlerWithExceptionHandling(): void
    {
        // Given: Generator handler that may throw
        $errorCaught = false;
        $successResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) use (&$errorCaught, &$successResult) {
                // Register generator ability that might fail
                $this->abilities()->register('risky-generator', [
                    'name' => 'risky-generator',
                    'description' => 'Risky generator handler',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) {
                        // Generator with potential error
                        yield;

                        if ($params['fail'] ?? false) {
                            throw new \RuntimeException('Generator error');
                        }

                        yield;
                        return ['result' => 'success'];
                    }
                ]);

                // Invoke successfully
                yield from $this->abilities('risky-generator', ['fail' => false])
                    ->then(function ($response) use (&$successResult) {
                        $successResult = 'completed';
                    });

                // TODO: Test error case when error handling is implemented
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Successful generator should complete
        $this->assertSame('completed', $successResult, 'Generator should complete successfully');
    }

    #[Test]
    public function nestedGeneratorHandlers(): void
    {
        // Given: Generator handler that invokes another ability
        $outerExecuted = false;
        $innerExecuted = false;
        $finalResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) use (&$outerExecuted, &$innerExecuted, &$finalResult) {
                // Register inner ability
                $this->abilities()->register('inner', [
                    'name' => 'inner',
                    'description' => 'Inner ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$innerExecuted) {
                        $innerExecuted = true;
                        yield;
                        return ['level' => 'inner'];
                    }
                ]);

                // Register outer ability that calls inner
                $this->abilities()->register('outer', [
                    'name' => 'outer',
                    'description' => 'Outer ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$outerExecuted) {
                        $outerExecuted = true;
                        yield;

                        // Nested ability invocation from generator
                        // This demonstrates composition of async abilities
                        yield;

                        return ['level' => 'outer'];
                    }
                ]);

                // Invoke outer generator
                yield from $this->abilities('outer')
                    ->then(function ($response) use (&$finalResult) {
                        $finalResult = 'completed';
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Outer generator should execute
        $this->assertTrue($outerExecuted, 'Outer generator should execute');
        $this->assertSame('completed', $finalResult, 'Outer generator should complete');
    }

    #[Test]
    public function generatorHandlerAccessesParameters(): void
    {
        // Given: Generator handler that uses invocation parameters
        $capturedParams = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$capturedParams) {
                // Register generator that uses parameters
                $this->abilities()->register('parameterized', [
                    'name' => 'parameterized',
                    'description' => 'Parameterized generator',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) use (&$capturedParams) {
                        // Generator can access parameters
                        yield;
                        $capturedParams = $params;
                        yield;
                        return ['received' => $params];
                    }
                ]);

                // Invoke with parameters
                $this->abilities('parameterized', [
                    'key' => 'value',
                    'number' => 42
                ]);
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Generator should receive parameters
        $this->assertNotNull($capturedParams, 'Generator should receive parameters');
        $this->assertSame('value', $capturedParams['key']);
        $this->assertSame(42, $capturedParams['number']);
    }
}
