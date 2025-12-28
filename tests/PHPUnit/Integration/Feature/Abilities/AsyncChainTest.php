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
 * Acceptance Criterion: Multiple then() callbacks with AsyncFeature
 *
 * Intent: Validates asynchronous execution of chained callbacks with event loop
 *
 * @see specs/features/abilities.yaml - integration (derived from spec analysis)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('async')]
class AsyncChainTest extends RegionBuilderTestCase
{
    #[Test]
    public function asyncCallbacksExecuteAfterYield(): void
    {
        // Given: Region with AsyncFeature enabled
        $callbackExecuted = false;
        $beforeYield = false;
        $afterYield = false;

        $region = $this->builder
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$callbackExecuted, &$beforeYield, &$afterYield) {
                // Register ability
                $this->abilities()->register('async-test', [
                    'name' => 'async-test',
                    'description' => 'Async execution test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['value' => 42]
                ]);

                // Invoke with async callback
                $this->abilities('async-test')
                    ->then(function ($response) use (&$callbackExecuted) {
                        $callbackExecuted = true;
                    });

                $beforeYield = true;

                // Yield to allow async processing
                yield;

                $afterYield = true;
            })
            ->build();

        // When: Trigger region and tick event loop
        $region->trigger((object)[]);

        // Then: Callback should execute during async processing
        $this->assertTrue($beforeYield);
        $this->assertTrue($callbackExecuted, 'Async callback should execute');
        $this->assertTrue($afterYield);
    }

    #[Test]
    public function multipleAsyncCallbacksExecute(): void
    {
        // Given: Multiple then() callbacks with AsyncFeature
        $executionCount = 0;

        $region = $this->builder
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$executionCount) {
                // Register ability
                $this->abilities()->register('multi-async', [
                    'name' => 'multi-async',
                    'description' => 'Multiple async callbacks',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                $message = $this->abilities('multi-async');

                $message->then(function () use (&$executionCount) {
                    $executionCount++;
                });

                $message->then(function () use (&$executionCount) {
                    $executionCount++;
                });

                $message->then(function () use (&$executionCount) {
                    $executionCount++;
                });

                // Yield to process
                yield;
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All callbacks should execute
        $this->assertSame(3, $executionCount);
    }

    #[Test]
    public function generatorHandlerWithAsync(): void
    {
        // Given: Ability handler that is a generator
        $response = null;

        $region = $this->builder
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$response) {
                // Register ability with generator handler
                $this->abilities()->register('generator-handler', [
                    'name' => 'generator-handler',
                    'description' => 'Generator handler',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        yield; // Async operation
                        return ['async' => true];
                    }
                ]);

                // Invoke and capture response
                $this->abilities('generator-handler')
                    ->then(function ($data) use (&$response) {
                        $response = $data;
                    });

                // Yield to allow async processing
                yield;
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Generator handler should execute and deliver response
        $this->assertNotNull($response);
        $this->assertInstanceOf(\Noem\State\Feature\Abilities\Message\AbilityMessage::class, $response);
        $this->assertTrue($response->parameters['async']);
    }

    #[Test]
    public function yieldingAbilityInvocationWaitsForResponse(): void
    {
        // Given: Yielding ability invocation
        $responseReceived = null;

        $region = $this->builder
            ->enableFeatures(
                new ExtendedState(),
                new MessageFeature(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$responseReceived) {
                // Register ability
                $this->abilities()->register('yield-test', [
                    'name' => 'yield-test',
                    'description' => 'Yield wait test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        yield;
                        return ['waited' => true];
                    }
                ]);

                // Yield the ability invocation to wait for response
                $message = $this->abilities('yield-test');

                // Register then() to capture response
                $message->then(function ($data) use (&$responseReceived) {
                    $responseReceived = $data;
                });

                // Yield to wait
                yield;
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Response should be received after yielding
        $this->assertNotNull($responseReceived);
        $this->assertInstanceOf(\Noem\State\Feature\Abilities\Message\AbilityMessage::class, $responseReceived);
        $this->assertTrue($responseReceived->parameters['waited']);
    }
}
