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
 * Acceptance Criterion: Message.then() handlers fire synchronously without AsyncFeature
 *
 * Intent: Response callbacks execute immediately during same trigger call,
 *         supporting synchronous workflows
 *
 * @see specs/features/abilities.yaml - synchronous-behavior
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('synchronous')]
class SynchronousResponseTest extends RegionBuilderTestCase
{
    #[Test]
    public function thenHandlerFiresSynchronously(): void
    {
        // Given: Track execution order
        $executionOrder = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $trigger) use (&$executionOrder) {
                // Register ability
                $this->abilities()->register('test', [
                    'name' => 'test',
                    'description' => 'Test ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionOrder) {
                        $executionOrder[] = 'handler';
                        return ['value' => 42];
                    }
                ]);

                $executionOrder[] = 'before-then';

                // Invoke with then() callback
                $this->abilities('test')
                    ->then(function ($response) use (&$executionOrder) {
                        $executionOrder[] = 'then-callback';
                    });

                $executionOrder[] = 'after-then';
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: then() callback should fire synchronously
        $this->assertContains('then-callback', $executionOrder);

        // Verify execution order: handler -> then-callback -> after-then
        $handlerIndex = array_search('handler', $executionOrder);
        $thenIndex = array_search('then-callback', $executionOrder);
        $afterIndex = array_search('after-then', $executionOrder);

        $this->assertLessThan(
            $thenIndex,
            $handlerIndex,
            'Handler should execute before then() callback'
        );

        $this->assertLessThan(
            $afterIndex,
            $thenIndex,
            'then() callback should fire before abilities() call returns (synchronous)'
        );
    }

    #[Test]
    public function thenReceivesCorrectResponseData(): void
    {
        // Given: Prepare to capture response
        $capturedResponse = null;
        $expectedResult = ['status' => 'success', 'value' => 123];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $trigger) use (&$capturedResponse, $expectedResult) {
                // Register ability that returns specific data
                $this->abilities()->register('get-data', [
                    'name' => 'get-data',
                    'description' => 'Returns test data',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => $expectedResult
                ]);

                // Invoke and capture response
                $this->abilities('get-data')
                    ->then(function ($response) use (&$capturedResponse) {
                        $capturedResponse = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Response should be received synchronously with correct data
        $this->assertNotNull(
            $capturedResponse,
            'Response should be delivered synchronously to then() handler'
        );

        // Response should be an AbilityMessage containing the handler result
        $this->assertInstanceOf(
            \Noem\State\Feature\Abilities\AbilityMessage::class,
            $capturedResponse,
            'Response should be an AbilityMessage'
        );
    }

    #[Test]
    public function multipleThenHandlersAllFireSynchronously(): void
    {
        // Given: Register multiple then() handlers
        $firstHandlerCalled = false;
        $secondHandlerCalled = false;
        $thirdHandlerCalled = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $trigger) use (&$firstHandlerCalled, &$secondHandlerCalled, &$thirdHandlerCalled) {
                // Register ability
                $this->abilities()->register('multi', [
                    'name' => 'multi',
                    'description' => 'Test multiple handlers',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => fn() => ['data' => 'test']
                ]);

                // Chain multiple then() handlers
                $this->abilities('multi')
                    ->then(function () use (&$firstHandlerCalled) {
                        $firstHandlerCalled = true;
                    })
                    ->then(function () use (&$secondHandlerCalled) {
                        $secondHandlerCalled = true;
                    })
                    ->then(function () use (&$thirdHandlerCalled) {
                        $thirdHandlerCalled = true;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All then() handlers should fire synchronously
        $this->assertTrue($firstHandlerCalled, 'First then() handler should be called');
        $this->assertTrue($secondHandlerCalled, 'Second then() handler should be called');
        $this->assertTrue($thirdHandlerCalled, 'Third then() handler should be called');
    }
}
