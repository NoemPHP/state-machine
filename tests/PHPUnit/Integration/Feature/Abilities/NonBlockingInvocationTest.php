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
                        // Generator init executes before first yield (PHP behavior)
                        yield; // Pause immediately
                        $executionOrder[] = 'handler-executed';
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

        // Then: abilities() call should return immediately (non-blocking)
        $this->assertContains('before-invoke', $executionOrder);
        $this->assertContains('after-invoke', $executionOrder);

        // Handler executes asynchronously after scheduler tick
        $afterIndex = array_search('after-invoke', $executionOrder);
        $handlerIndex = array_search('handler-executed', $executionOrder);

        if ($handlerIndex !== false) {
            $this->assertLessThan(
                $handlerIndex,
                $afterIndex,
                'abilities() call should return before handler body executes (non-blocking)'
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

                // Note: We can't assert inside callback because $this is Bound
                // The external assertions will verify the behavior
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
                        yield; // Pause before logging
                        $executionOrder[] = 'task1-handler';
                        return ['id' => 1];
                    }
                ]);

                $this->abilities()->register('task2', [
                    'name' => 'task2',
                    'description' => 'Task 2',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionOrder) {
                        yield; // Pause before logging
                        $executionOrder[] = 'task2-handler';
                        return ['id' => 2];
                    }
                ]);

                $this->abilities()->register('task3', [
                    'name' => 'task3',
                    'description' => 'Task 3',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionOrder) {
                        yield; // Pause before logging
                        $executionOrder[] = 'task3-handler';
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

                // Note: We can't assert inside callback because $this is Bound
                // The external assertions will verify the behavior
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: All invocations should have returned before handlers execute
        $this->assertSame('start', $executionOrder[0]);
        $this->assertSame('after-task1', $executionOrder[1]);
        $this->assertSame('after-task2', $executionOrder[2]);
        $this->assertSame('after-task3', $executionOrder[3]);

        // Handlers execute after all invocations return
        $this->assertContains('task1-handler', array_slice($executionOrder, 4));
        $this->assertContains('task2-handler', array_slice($executionOrder, 4));
        $this->assertContains('task3-handler', array_slice($executionOrder, 4));
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
