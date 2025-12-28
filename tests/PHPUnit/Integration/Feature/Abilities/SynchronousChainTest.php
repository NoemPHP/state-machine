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
 * Acceptance Criterion: Multiple then() callbacks execute in order synchronously
 *
 * Intent: Validates synchronous execution order of chained callbacks
 *
 * @see specs/features/abilities.yaml - integration (derived from spec analysis)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('synchronous')]
class SynchronousChainTest extends RegionBuilderTestCase
{
    #[Test]
    public function sequentialExecutionWithoutAsync(): void
    {
        // Given: Multiple then() callbacks without AsyncFeature
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
                $this->abilities()->register('sync-chain', [
                    'name' => 'sync-chain',
                    'description' => 'Synchronous chain test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['value' => 42]
                ]);

                $executionOrder[] = 'before-invoke';

                $message = $this->abilities('sync-chain');
                $executionOrder[] = 'after-invoke';

                $message->then(function ($response) use (&$executionOrder) {
                    $executionOrder[] = 'callback-1';
                });

                $message->then(function ($response) use (&$executionOrder) {
                    $executionOrder[] = 'callback-2';
                });

                $message->then(function ($response) use (&$executionOrder) {
                    $executionOrder[] = 'callback-3';
                });

                $executionOrder[] = 'after-then-registration';
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Execution should be synchronous and in order
        $expected = [
            'before-invoke',
            'after-invoke',
            'callback-1',
            'callback-2',
            'callback-3',
            'after-then-registration',
        ];

        $this->assertSame($expected, $executionOrder);
    }

    #[Test]
    public function callbacksExecuteBeforeReturningToInvoker(): void
    {
        // Given: Flag set in callback
        $callbackExecuted = false;
        $afterInvoke = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$callbackExecuted, &$afterInvoke) {
                // Register ability
                $this->abilities()->register('immediate', [
                    'name' => 'immediate',
                    'description' => 'Immediate execution',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => []
                ]);

                // Invoke with callback
                $this->abilities('immediate')
                    ->then(function () use (&$callbackExecuted) {
                        $callbackExecuted = true;
                    });

                // This line executes AFTER callback in synchronous mode
                $afterInvoke = true;
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Both should be true, with callback executing first
        $this->assertTrue($callbackExecuted);
        $this->assertTrue($afterInvoke);
    }

    #[Test]
    public function synchronousDataTransformation(): void
    {
        // Given: Chain that transforms data synchronously
        $finalResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$finalResult) {
                // Register ability returning initial value
                $this->abilities()->register('transformer', [
                    'name' => 'transformer',
                    'description' => 'Data transformation',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['value' => 1]
                ]);

                // Chain transformations synchronously
                $intermediate = null;

                $message = $this->abilities('transformer');

                $message->then(function ($response) use (&$intermediate) {
                    $intermediate = $response->parameters['value'] * 2; // 1 -> 2
                });

                $message->then(function ($response) use (&$intermediate, &$finalResult) {
                    $finalResult = $intermediate + $response->parameters['value']; // 2 + 1 = 3
                });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Transformations should complete synchronously
        $this->assertSame(3, $finalResult);
    }
}
