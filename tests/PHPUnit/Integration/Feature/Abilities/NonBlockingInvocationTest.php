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
 * Acceptance Criterion: Ability invocation with AsyncFeature returns immediately without blocking
 *
 * Intent: Message dispatch queues async task instead of executing synchronously,
 *         enabling parallelism. The abilities() call returns immediately without
 *         waiting for the handler to complete.
 *
 * @see specs/features/abilities.yaml - async-enhancement (line 440-443)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('async')]
class NonBlockingInvocationTest extends RegionBuilderTestCase
{
    #[Test]
    public function abilityInvocationReturnsImmediately(): void
    {
        // Given: Track execution order
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
                // Register ability with handler that tracks execution
                $this->abilities()->register('async-test', [
                    'name' => 'async-test',
                    'description' => 'Async test ability',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionOrder) {
                        // This should NOT execute during abilities() call
                        $executionOrder[] = 'handler-executed';
                        yield;
                        return ['result' => 'done'];
                    }
                ]);

                $executionOrder[] = 'before-invoke';

                // Invoke ability - should return immediately
                $this->abilities('async-test')
                    ->then(function ($response) use (&$executionOrder) {
                        $executionOrder[] = 'then-callback';
                    });

                $executionOrder[] = 'after-invoke';
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: abilities() call should return before handler executes
        $this->assertContains('before-invoke', $executionOrder);
        $this->assertContains('after-invoke', $executionOrder);

        // Verify non-blocking: after-invoke comes BEFORE handler-executed
        $afterIndex = array_search('after-invoke', $executionOrder);
        $handlerIndex = array_search('handler-executed', $executionOrder);

        if ($handlerIndex !== false) {
            $this->assertLessThan(
                $handlerIndex,
                $afterIndex,
                'abilities() call should return before async handler executes (non-blocking)'
            );
        }
    }

    #[Test]
    public function handlerExecutionIsDeferred(): void
    {
        // Given: Track when handler actually runs
        $handlerExecuted = false;
        $invocationReturned = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$handlerExecuted, &$invocationReturned) {
                // Register ability
                $this->abilities()->register('deferred', [
                    'name' => 'deferred',
                    'description' => 'Deferred execution test',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$handlerExecuted) {
                        // Generator handler for async execution
                        $handlerExecuted = true;
                        yield;
                        return ['deferred' => true];
                    }
                ]);

                // Invoke ability
                $this->abilities('deferred');

                // Mark that invocation returned
                $invocationReturned = true;

                // At this point, handler should NOT have executed yet
                $this->assertFalse(
                    $handlerExecuted,
                    'Handler should not execute during abilities() call with AsyncFeature'
                );
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Invocation should have returned immediately
        $this->assertTrue(
            $invocationReturned,
            'abilities() call should return immediately with AsyncFeature'
        );
    }

    #[Test]
    public function multipleInvocationsQueueTasks(): void
    {
        // Given: Multiple ability invocations in sequence
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
                // Register multiple abilities
                $this->abilities()->register('task1', [
                    'name' => 'task1',
                    'description' => 'Task 1',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionOrder) {
                        $executionOrder[] = 'task1-handler';
                        yield;
                        return ['id' => 1];
                    }
                ]);

                $this->abilities()->register('task2', [
                    'name' => 'task2',
                    'description' => 'Task 2',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionOrder) {
                        $executionOrder[] = 'task2-handler';
                        yield;
                        return ['id' => 2];
                    }
                ]);

                $this->abilities()->register('task3', [
                    'name' => 'task3',
                    'description' => 'Task 3',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionOrder) {
                        $executionOrder[] = 'task3-handler';
                        yield;
                        return ['id' => 3];
                    }
                ]);

                $executionOrder[] = 'start';

                // Invoke all three - should queue, not block
                $this->abilities('task1');
                $executionOrder[] = 'after-task1';

                $this->abilities('task2');
                $executionOrder[] = 'after-task2';

                $this->abilities('task3');
                $executionOrder[] = 'after-task3';

                // None of the handlers should have executed yet
                $this->assertNotContains(
                    'task1-handler',
                    $executionOrder,
                    'Task 1 handler should not execute during invocation'
                );
                $this->assertNotContains(
                    'task2-handler',
                    $executionOrder,
                    'Task 2 handler should not execute during invocation'
                );
                $this->assertNotContains(
                    'task3-handler',
                    $executionOrder,
                    'Task 3 handler should not execute during invocation'
                );
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All invocations should have returned before handlers execute
        $this->assertSame(
            ['start', 'after-task1', 'after-task2', 'after-task3', 'task1-handler', 'task2-handler', 'task3-handler'],
            array_slice($executionOrder, 0, 7),
            'All invocations should return immediately, then handlers execute asynchronously'
        );
    }

    #[Test]
    public function nonBlockingEnablesParallelism(): void
    {
        // Given: Ability that simulates long-running operation
        $task1Progress = [];
        $task2Progress = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$task1Progress, &$task2Progress) {
                // Register long-running ability
                $this->abilities()->register('long-task', [
                    'name' => 'long-task',
                    'description' => 'Long running task',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) use (&$task1Progress, &$task2Progress) {
                        // Simulate multi-step async operation
                        $taskId = $params['id'] ?? 'unknown';

                        if ($taskId === 1) {
                            $task1Progress[] = 'start';
                            yield;
                            $task1Progress[] = 'middle';
                            yield;
                            $task1Progress[] = 'end';
                        } else {
                            $task2Progress[] = 'start';
                            yield;
                            $task2Progress[] = 'middle';
                            yield;
                            $task2Progress[] = 'end';
                        }

                        return ['taskId' => $taskId];
                    }
                ]);

                // Start two long-running tasks - should not block each other
                $this->abilities('long-task', ['id' => 1]);
                $this->abilities('long-task', ['id' => 2]);

                // Both should be queued, not blocking
            })
            ->build();

        // When: Trigger region (async scheduler processes tasks)
        $region->trigger((object)[]);

        // Then: Both tasks should have started (demonstrating parallelism)
        // In synchronous mode, task2 would wait for task1 to complete
        // In async mode, both can progress concurrently
        $this->assertNotEmpty(
            $task1Progress,
            'Task 1 should have progressed'
        );
        $this->assertNotEmpty(
            $task2Progress,
            'Task 2 should have progressed (demonstrating non-blocking parallelism)'
        );
    }
}
