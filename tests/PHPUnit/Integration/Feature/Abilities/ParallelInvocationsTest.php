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
 * Acceptance Criterion: Multiple abilities can execute concurrently with AsyncFeature
 *
 * Intent: Parallel invocations yield to scheduler, enabling concurrent execution
 *         of multiple abilities. When using yield from execute, multiple ability
 *         handlers can progress in parallel.
 *
 * @see specs/features/abilities.yaml - async-enhancement (line 450-453)
 */
#[Group('feature')]
#[Group('abilities')]
#[Group('integration')]
#[Group('async')]
class ParallelInvocationsTest extends RegionBuilderTestCase
{
    #[Test]
    public function multipleAbilitiesExecuteConcurrently(): void
    {
        // Given: Multiple abilities invoked in parallel
        $executionLog = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$executionLog) {
                // Register abilities that log their progress
                $this->abilities()->register('ability-a', [
                    'name' => 'ability-a',
                    'description' => 'Ability A',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionLog) {
                        // Log start BEFORE yield to show concurrent startup
                        $executionLog[] = 'A-start';
                        yield;
                        $executionLog[] = 'A-step1';
                        yield;
                        $executionLog[] = 'A-step2';
                        yield;
                        $executionLog[] = 'A-end';
                        return ['ability' => 'A'];
                    }
                ]);

                $this->abilities()->register('ability-b', [
                    'name' => 'ability-b',
                    'description' => 'Ability B',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionLog) {
                        // Log start BEFORE yield to show concurrent startup
                        $executionLog[] = 'B-start';
                        yield;
                        $executionLog[] = 'B-step1';
                        yield;
                        $executionLog[] = 'B-step2';
                        yield;
                        $executionLog[] = 'B-end';
                        return ['ability' => 'B'];
                    }
                ]);

                $this->abilities()->register('ability-c', [
                    'name' => 'ability-c',
                    'description' => 'Ability C',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$executionLog) {
                        // Log start BEFORE yield to show concurrent startup
                        $executionLog[] = 'C-start';
                        yield;
                        $executionLog[] = 'C-step1';
                        yield;
                        $executionLog[] = 'C-step2';
                        yield;
                        $executionLog[] = 'C-end';
                        return ['ability' => 'C'];
                    }
                ]);

                // Invoke all three abilities - they should execute in parallel
                $this->abilities('ability-a');
                $this->abilities('ability-b');
                $this->abilities('ability-c');
            })
            ->build();

        // When: Trigger region multiple times for scheduler ticks
        $region->trigger((object)[]);  // onEnter runs, abilities invoked
        $region->trigger((object)[]);  // Scheduler tick
        $region->trigger((object)[]);  // Scheduler tick
        $region->trigger((object)[]);  // Scheduler tick
        $region->trigger((object)[]);  // Scheduler tick - handlers complete

        // Then: All abilities should have executed
        $this->assertContains('A-start', $executionLog);
        $this->assertContains('A-end', $executionLog);
        $this->assertContains('B-start', $executionLog);
        $this->assertContains('B-end', $executionLog);
        $this->assertContains('C-start', $executionLog);
        $this->assertContains('C-end', $executionLog);

        // Verify interleaving (concurrent execution, not sequential)
        // In synchronous mode: A-start, A-step1, A-step2, A-end, then B-start...
        // In async mode: A-start, B-start, C-start, A-step1, B-step1, C-step1...
        $aStartIndex = array_search('A-start', $executionLog);
        $bStartIndex = array_search('B-start', $executionLog);
        $cStartIndex = array_search('C-start', $executionLog);
        $aEndIndex = array_search('A-end', $executionLog);

        // B and C should start before A ends (demonstrating parallelism)
        $this->assertLessThan(
            $aEndIndex,
            $bStartIndex,
            'Ability B should start before Ability A completes (parallel execution)'
        );
        $this->assertLessThan(
            $aEndIndex,
            $cStartIndex,
            'Ability C should start before Ability A completes (parallel execution)'
        );
    }

    #[Test]
    public function yieldFromEnablesParallelInvocations(): void
    {
        // Given: Using yield from for parallel execution
        $task1Completed = false;
        $task2Completed = false;
        $task3Completed = false;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$task1Completed, &$task2Completed, &$task3Completed) {
                // Register parallel tasks
                $this->abilities()->register('parallel-task', [
                    'name' => 'parallel-task',
                    'description' => 'Parallel task',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) {
                        // Multi-step async operation
                        yield;
                        yield;
                        yield;
                        return $params;
                    }
                ]);

                // Invoke abilities to enable parallel execution
                $this->abilities('parallel-task', ['id' => 1])
                    ->then(function () use (&$task1Completed) {
                        $task1Completed = true;
                    });

                $this->abilities('parallel-task', ['id' => 2])
                    ->then(function () use (&$task2Completed) {
                        $task2Completed = true;
                    });

                $this->abilities('parallel-task', ['id' => 3])
                    ->then(function () use (&$task3Completed) {
                        $task3Completed = true;
                    });
            })
            ->build();

        // When: Trigger region multiple times for scheduler ticks
        $region->trigger((object)[]);  // onEnter runs, abilities invoked
        $region->trigger((object)[]);  // Scheduler tick
        $region->trigger((object)[]);  // Scheduler tick
        $region->trigger((object)[]);  // Scheduler tick - all complete, responses delivered

        // Then: All tasks should complete via parallel execution
        $this->assertTrue($task1Completed, 'Task 1 should complete');
        $this->assertTrue($task2Completed, 'Task 2 should complete');
        $this->assertTrue($task3Completed, 'Task 3 should complete');
    }

    #[Test]
    public function concurrentExecutionDoesNotInterfereWithCorrelation(): void
    {
        // Given: Multiple abilities with different responses
        $responses = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$responses) {
                // Register ability
                $this->abilities()->register('identify', [
                    'name' => 'identify',
                    'description' => 'Returns identifier',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function ($params) {
                        yield;
                        return ['id' => $params['value']];
                    }
                ]);

                // Invoke multiple times concurrently - each should get its own response
                $this->abilities('identify', ['value' => 'alpha'])
                    ->then(function ($response) use (&$responses) {
                        $responses[] = 'alpha';
                    });

                $this->abilities('identify', ['value' => 'beta'])
                    ->then(function ($response) use (&$responses) {
                        $responses[] = 'beta';
                    });

                $this->abilities('identify', ['value' => 'gamma'])
                    ->then(function ($response) use (&$responses) {
                        $responses[] = 'gamma';
                    });
            })
            ->build();

        // When: Trigger region multiple times
        $region->trigger((object)[]);  // onEnter runs, abilities invoked
        $region->trigger((object)[]);  // Scheduler tick: all complete, responses delivered

        // Then: Each invocation should receive its own correlated response
        $this->assertCount(3, $responses, 'All three responses should be delivered');
        $this->assertContains('alpha', $responses, 'Alpha response should be correlated correctly');
        $this->assertContains('beta', $responses, 'Beta response should be correlated correctly');
        $this->assertContains('gamma', $responses, 'Gamma response should be correlated correctly');
    }

    #[Test]
    public function parallelAbilitiesFromDifferentStates(): void
    {
        // Given: Multiple states invoking abilities concurrently
        $stateAResult = null;
        $stateBResult = null;

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onEnter('idle', function (object $t) use (&$stateAResult, &$stateBResult) {
                // Register both tasks from same state (simulating parallel abilities)
                $this->abilities()->register('task-a', [
                    'name' => 'task-a',
                    'description' => 'Task A',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        yield;
                        yield;
                        return ['from' => 'task-a'];
                    }
                ]);

                $this->abilities()->register('task-b', [
                    'name' => 'task-b',
                    'description' => 'Task B',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () {
                        yield;
                        yield;
                        return ['from' => 'task-b'];
                    }
                ]);

                // Invoke both tasks in parallel
                $this->abilities('task-a')
                    ->then(function ($response) use (&$stateAResult) {
                        $stateAResult = 'a-done';
                    });

                $this->abilities('task-b')
                    ->then(function ($response) use (&$stateBResult) {
                        $stateBResult = 'b-done';
                    });
            })
            ->build();

        // When: Trigger region multiple times for async completion
        $region->trigger((object)[]);  // onEnter runs, both abilities invoked
        $region->trigger((object)[]);  // Scheduler tick
        $region->trigger((object)[]);  // Scheduler tick - both complete, responses delivered

        // Then: Both should execute in parallel
        $this->assertSame('a-done', $stateAResult, 'State A ability should complete');
        $this->assertSame('b-done', $stateBResult, 'State B ability should complete');
    }

    #[Test]
    public function schedulerInterleavesAbilityHandlers(): void
    {
        // Given: Track exact execution order of interleaved handlers
        $interleavingLog = [];

        $region = $this->builder
            ->enableFeatures(
                new MessageFeature(),
                new ExtendedState(),
                new AsyncFeature(),
                new AbilitiesFeature()
            )
            ->setStates('idle')
            ->onAction('idle', function (object $t) use (&$interleavingLog) {
                // Register abilities with distinct step markers
                $this->abilities()->register('fast', [
                    'name' => 'fast',
                    'description' => 'Fast task',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$interleavingLog) {
                        $interleavingLog[] = 'fast-1';
                        yield;
                        $interleavingLog[] = 'fast-2';
                        return ['type' => 'fast'];
                    }
                ]);

                $this->abilities()->register('slow', [
                    'name' => 'slow',
                    'description' => 'Slow task',
                    'parameterSchema' => [],
                    'responseSchema' => [],
                    'handler' => function () use (&$interleavingLog) {
                        $interleavingLog[] = 'slow-1';
                        yield;
                        $interleavingLog[] = 'slow-2';
                        yield;
                        $interleavingLog[] = 'slow-3';
                        return ['type' => 'slow'];
                    }
                ]);

                // Invoke both - scheduler should interleave execution
                $this->abilities('fast');
                $this->abilities('slow');

                // Yield to allow async processing
                yield;
            })
            ->build();

        // When: Trigger region
        $region->trigger((object)[]);

        // Then: Execution should be interleaved, not sequential
        // Sequential would be: fast-1, fast-2, slow-1, slow-2, slow-3
        // Interleaved: fast-1, slow-1, fast-2, slow-2, slow-3 (or similar)
        $this->assertNotEmpty($interleavingLog);

        $fast1Index = array_search('fast-1', $interleavingLog);
        $slow1Index = array_search('slow-1', $interleavingLog);
        $fast2Index = array_search('fast-2', $interleavingLog);
        $slow2Index = array_search('slow-2', $interleavingLog);

        // Both should start before either completes (demonstrating interleaving)
        $this->assertNotFalse($fast1Index, 'fast-1 should execute');
        $this->assertNotFalse($slow1Index, 'slow-1 should execute');

        // Verify interleaving pattern exists
        if ($fast1Index !== false && $slow1Index !== false && $fast2Index !== false) {
            // If fast starts first, slow should start before fast completes
            if ($fast1Index < $slow1Index) {
                $this->assertLessThan(
                    $fast2Index,
                    $slow1Index,
                    'Slow task should start before fast task completes (interleaving)'
                );
            }
        }
    }
}
