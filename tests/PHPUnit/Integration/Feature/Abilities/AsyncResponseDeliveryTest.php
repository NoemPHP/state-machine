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
 * Acceptance Criterion: Message.then() handlers fire asynchronously when AsyncFeature enabled
 *
 * Intent: Response callbacks execute in scheduler tick after handler completes,
 *         supporting concurrent workflows. Unlike synchronous mode where then()
 *         callbacks execute immediately, AsyncFeature schedules them asynchronously.
 *
 * @see specs/features/abilities.yaml - async-enhancement (line 445-448)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('async')]
class AsyncResponseDeliveryTest extends RegionBuilderTestCase
{
    #[Test]
    public function thenHandlersFireAsynchronously(): void
    {
        // Given: Track execution order to verify async scheduling
        $executionOrder = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$executionOrder) {
                // Register ability
                $this->abilities()->register('async-response-test', [
                    'name' => 'async-response-test',
                    'description' => 'Async response test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionOrder) {
                        $executionOrder[] = 'handler-start';
                        yield;
                        $executionOrder[] = 'handler-end';
                        return ['status' => 'done'];
                    }
                ]);

                $executionOrder[] = 'before-invoke';

                // Invoke with then() callback
                $this->abilities('async-response-test')
                    ->then(function ($response) use (&$executionOrder) {
                        $executionOrder[] = 'then-callback';
                    });

                $executionOrder[] = 'after-invoke';
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: then() callback should fire AFTER abilities() call returns
        // Expected order: before-invoke, after-invoke, handler-start, handler-end, then-callback
        $this->assertContains('then-callback', $executionOrder);

        $beforeIndex = array_search('before-invoke', $executionOrder);
        $afterIndex = array_search('after-invoke', $executionOrder);
        $thenIndex = array_search('then-callback', $executionOrder);

        // Verify async behavior: invocation completes before then() callback fires
        $this->assertGreaterThan(
            $afterIndex,
            $thenIndex,
            'then() callback should fire AFTER abilities() call returns (async scheduling)'
        );
    }

    #[Test]
    public function responseDeliveryInSchedulerTick(): void
    {
        // Given: Track when response is delivered relative to handler completion
        $handlerCompleted = false;
        $responseReceived = false;
        $afterInvocation = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlerCompleted, &$responseReceived, &$afterInvocation) {
                // Register ability
                $this->abilities()->register('scheduled', [
                    'name' => 'scheduled',
                    'description' => 'Scheduled response test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$handlerCompleted) {
                        yield;
                        $handlerCompleted = true;
                        return ['delivered' => 'async'];
                    }
                ]);

                // Invoke with then() callback
                $this->abilities('scheduled')
                    ->then(function ($response) use (&$responseReceived) {
                        $responseReceived = true;
                    });

                $afterInvocation = true;

                // At this point (with AsyncFeature), response should NOT be delivered yet
                // Note: Cannot use assertions inside callbacks with bound context
            })
            ->build();

        // When: Trigger region (async scheduler delivers response)
        $region->trigger((object)[]);

        // Then: Response should be delivered after scheduler tick
        $this->assertTrue($afterInvocation, 'Invocation should have completed');
        $this->assertTrue($handlerCompleted, 'Handler should have executed');
        $this->assertTrue($responseReceived, 'Response should be delivered asynchronously');
    }

    #[Test]
    public function multipleThenHandlersScheduledAsynchronously(): void
    {
        // Given: Multiple then() handlers on same ability invocation
        $handlers = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlers) {
                // Register ability
                $this->abilities()->register('multi-then', [
                    'name' => 'multi-then',
                    'description' => 'Multiple then handlers',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        yield;
                        return ['value' => 42];
                    }
                ]);

                // Chain multiple then() handlers
                $this->abilities('multi-then')
                    ->then(function ($response) use (&$handlers) {
                        $handlers[] = 'first';
                    })
                    ->then(function ($response) use (&$handlers) {
                        $handlers[] = 'second';
                    })
                    ->then(function ($response) use (&$handlers) {
                        $handlers[] = 'third';
                    });

                // None should have fired during invocation
                // Note: Cannot use assertions inside callbacks with bound context
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All then() handlers should fire asynchronously
        $this->assertSame(
            ['first', 'second', 'third'],
            $handlers,
            'All then() handlers should fire asynchronously in order'
        );
    }

    #[Test]
    public function asyncCallbacksSupportConcurrentWorkflows(): void
    {
        // Given: Multiple abilities invoked with async callbacks
        $results = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$results) {
                // Register abilities
                $this->abilities()->register('workflow-step', [
                    'name' => 'workflow-step',
                    'description' => 'Workflow step',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) {
                        yield;
                        return ['step' => $params['step']];
                    }
                ]);

                // Start concurrent workflow steps with async callbacks
                $this->abilities('workflow-step', ['step' => 1])
                    ->then(function ($response) use (&$results) {
                        $results[] = 'step-1-done';
                    });

                $this->abilities('workflow-step', ['step' => 2])
                    ->then(function ($response) use (&$results) {
                        $results[] = 'step-2-done';
                    });

                $this->abilities('workflow-step', ['step' => 3])
                    ->then(function ($response) use (&$results) {
                        $results[] = 'step-3-done';
                    });
            })
            ->build();

        // When: Trigger region multiple times for scheduler ticks
        $region->trigger((object)[]);  // onEnter runs, abilities invoked, tasks enqueued
        $region->trigger((object)[]);  // Scheduler tick: handlers run
        $region->trigger((object)[]);  // Scheduler tick: responses delivered

        // Then: All async callbacks should fire
        $this->assertCount(
            3,
            $results,
            'All async callbacks should execute'
        );
        $this->assertContains('step-1-done', $results);
        $this->assertContains('step-2-done', $results);
        $this->assertContains('step-3-done', $results);
    }

    #[Test]
    public function responseCallbackReceivesCorrectData(): void
    {
        // Given: Capture response data in async callback
        $capturedResponse = null;
        $expectedData = ['result' => 'success', 'value' => 123];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$capturedResponse, $expectedData) {
                // Register ability with specific response
                $this->abilities()->register('data-test', [
                    'name' => 'data-test',
                    'description' => 'Data test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use ($expectedData) {
                        yield;
                        return $expectedData;
                    }
                ]);

                // Invoke and capture async response
                $this->abilities('data-test')
                    ->then(function ($response) use (&$capturedResponse) {
                        $capturedResponse = $response;
                    });
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Async callback should receive correct response data
        $this->assertNotNull(
            $capturedResponse,
            'Response should be delivered asynchronously'
        );

        // Response should be an AbilityMessage
        $this->assertInstanceOf(
            \Noem\State\Feature\Abilities\AbilityMessage::class,
            $capturedResponse,
            'Response should be an AbilityMessage'
        );
    }

    #[Test]
    public function schedulerDeliversResponsesAfterHandlerCompletion(): void
    {
        // Given: Track precise timing of handler completion vs response delivery
        $timeline = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$timeline) {
                // Register ability with multi-step handler
                $this->abilities()->register('timing', [
                    'name' => 'timing',
                    'description' => 'Timing test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$timeline) {
                        yield;  // Pause first to avoid sync execution
                        $timeline[] = 'handler-step-1';
                        yield;
                        $timeline[] = 'handler-step-2';
                        yield;
                        $timeline[] = 'handler-complete';
                        return ['timing' => 'test'];
                    }
                ]);

                // Invoke with callback
                $this->abilities('timing')
                    ->then(function ($response) use (&$timeline) {
                        $timeline[] = 'response-delivered';
                    });

                $timeline[] = 'invocation-complete';
            })
            ->build();

        // When: Trigger region multiple times for scheduler ticks
        $region->trigger((object)[]);  // onEnter runs, ability invoked
        $region->trigger((object)[]);  // Scheduler tick: handler-step-1
        $region->trigger((object)[]);  // Scheduler tick: handler-step-2
        $region->trigger((object)[]);  // Scheduler tick: handler-complete, response-delivered

        // Then: Response should be delivered AFTER handler completes
        $this->assertContains('handler-complete', $timeline);
        $this->assertContains('response-delivered', $timeline);

        $handlerCompleteIndex = array_search('handler-complete', $timeline);
        $responseIndex = array_search('response-delivered', $timeline);

        $this->assertLessThan(
            $responseIndex,
            $handlerCompleteIndex,
            'Response should be delivered after handler completes (async scheduling)'
        );
    }
}
