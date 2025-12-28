<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

class CoroutineScheduler
{
    /**
     * @var \SplPriorityQueue<int, Task>
     */
    private \SplPriorityQueue $queue;

    /**
     * @var \WeakMap<\Generator, Task>
     */
    private \WeakMap $generatorToTaskMap;

    /**
     * @var \WeakMap<callable, Task>
     */
    private \WeakMap $callbackTaskMap;

    private ?Task $currentTask = null;

    private Task|null $lastAddedTask = null;

    private bool $isBusy = false;

    public function __construct()
    {
        $this->queue = new \SplPriorityQueue();
        $this->generatorToTaskMap = new \WeakMap();
        $this->callbackTaskMap = new \WeakMap();
    }

    public function enqueue(
        \Generator $listener,
        ?AsyncConfig $config = null,
        ?callable $callback = null
    ): Task {
        // Handle singleton behavior
        // Throttle and debounce semantically require singleton behavior:
        // - Throttle: "at most once per period" means only one task instance
        // - Debounce: "wait for quiet period" requires resetting timer on same task
        $impliesSingleton = $config !== null && (
            $config->singleton ||
            $config->throttle !== null ||
            $config->debounce !== null
        );

        if ($impliesSingleton && $callback !== null && isset($this->callbackTaskMap[$callback])) {
            $existingTask = $this->callbackTaskMap[$callback];

            // CRITICAL: For debounce/throttle, check timing BEFORE calling isFinished()
            // Calling isFinished() triggers generator->valid() which executes code before first yield
            if ($config !== null) {
                $now = microtime(true);

                // Check debounce timing first (most restrictive)
                if ($config->debounce !== null) {
                    $enqueueTime = $existingTask->getDebounceTime() ?? 0;
                    if ($enqueueTime > 0) {
                        // Reset timer on new trigger
                        $existingTask->setDebounceTime($now);
                        // Replace generator to capture latest payload
                        $existingTask->replaceCoroutine($listener);
                        // Return existing task (now with new generator) without checking if finished
                        return $existingTask;
                    }
                }

                // Check throttle timing
                if ($config->throttle !== null) {
                    $lastExecution = $existingTask->getLastExecutionTime() ?? 0;
                    if ($lastExecution > 0 && $now - $lastExecution < $config->throttle) {
                        // Still within throttle period - capture latest payload but don't execute
                        $existingTask->replaceCoroutine($listener);
                        return $existingTask;
                    } elseif ($lastExecution > 0) {
                        // Throttle period elapsed - cancel existing task and create new one
                        // This ensures current trigger executes (not the captured one from throttle period)
                        $this->cancel($existingTask);
                        // Fall through to create new task
                        // (don't return - let execution continue to create new task below)
                    }
                    // If lastExecution is 0, this is the first trigger - fall through to create task
                }
            }

            // If we reach here and existing task is still in map, check if finished
            if (isset($this->callbackTaskMap[$callback])) {
                // Now safe to check if finished (timing requirements met or explicit singleton)
                if (!$existingTask->isFinished()) {
                    // Task still running
                    return $existingTask;
                }

                // Task finished and timing requirements met - remove old task and create new one
                unset($this->callbackTaskMap[$callback]);
            }
        }

        // Check for existing task by generator
        if ($task = $this->getTaskForCoroutine($listener)) {
            // Reset debounce timer if debounce is configured
            if ($config !== null && $config->debounce !== null) {
                $task->setDebounceTime(microtime(true));
            }
            return $task;
        }

        $priority = $config !== null ? $config->priority : Priority::NORMAL;
        // Backward compat: tasks without explicit config get 1 step per tick
        $priorityValue = $config !== null ? $config->priority->value : 1;

        $task = new Task($listener, $priority);
        $this->queue->insert($task, $priorityValue);
        $this->generatorToTaskMap[$listener] = $task;
        $task->markEnqueued();

        // Only store config if explicitly provided
        if ($config !== null) {
            $task->setConfig($config);
        }

        $this->lastAddedTask = $task;

        // Track callback for singleton behavior
        if ($callback !== null) {
            $this->callbackTaskMap[$callback] = $task;
        }

        // Record enqueue time for debounce/timeout tracking
        if ($config !== null && ($config->debounce !== null || $config->timeout !== null)) {
            $task->setDebounceTime(microtime(true));
        }

        return $task;
    }

    public function getTaskForCoroutine(\Generator $generator): ?Task
    {
        return $this->generatorToTaskMap[$generator] ?? null;
    }

    public function contains(\Generator $coroutine): bool
    {
        return $this->getTaskForCoroutine($coroutine) !== null;
    }

    public function cancel(Task $task): void
    {
        // Mark task as cancelled
        $task->cancel();

        // Remove from callback map
        foreach ($this->callbackTaskMap as $callback => $mappedTask) {
            if ($mappedTask === $task) {
                unset($this->callbackTaskMap[$callback]);
                break;
            }
        }

        // Trigger completion callbacks
        $task->triggerCompletionCallbacks();
    }

    public function tick(): void
    {
        if ($this->isBusy) {
            return;
        }
        $this->isBusy = true;

        // Clone queue for iteration (SplPriorityQueue is destructive)
        $tasks = [];
        $queueClone = clone $this->queue;
        while (!$queueClone->isEmpty()) {
            $tasks[] = $queueClone->extract();
        }


        $now = microtime(true);
        $finished = [];
        $processedInThisTick = new \WeakMap(); // Track which tasks we've already processed

        // Use while loop instead of foreach to allow processing newly added tasks
        $i = 0;
        while ($i < count($tasks)) {
            $task = $tasks[$i];
            $i++;

            // Skip if we've already processed this task in this tick
            if (isset($processedInThisTick[$task])) {
                continue;
            }
            $processedInThisTick[$task] = true;

            // Skip cancelled tasks
            if ($task->isCancelled()) {
                $finished[] = $task;
                continue;
            }

            if ($task->isPaused()) {
                continue;
            }

            $config = $task->getConfig();

            // Check timeout
            if ($config !== null && $config->timeout !== null) {
                $enqueueTime = $task->getDebounceTime() ?? $now;
                $elapsed = $now - $enqueueTime;
                if ($elapsed > $config->timeout) {
                    $finished[] = $task;
                    continue;
                }
            }

            // Check debounce
            if ($config !== null && $config->debounce !== null) {
                $enqueueTime = $task->getDebounceTime() ?? $now;
                if ($now - $enqueueTime < $config->debounce) {
                    continue; // Still debouncing
                }
            }

            // Check throttle
            if ($config !== null && $config->throttle !== null) {
                $lastExecution = $task->getLastExecutionTime() ?? 0;
                if ($now - $lastExecution < $config->throttle) {
                    continue; // Still throttled
                }
            }

            // CRITICAL: Check if task is finished ONLY AFTER timing checks
            // Calling isFinished() triggers generator->valid() which EXECUTES code before first yield
            // For debounce/throttle to work, we must check timing BEFORE letting the generator start
            if ($task->isFinished()) {
                // For throttled/debounced tasks, keep them around until the period elapses
                // so that enqueue() can check timing and prevent new tasks from being created
                $keepForTiming = false;
                if ($config !== null) {
                    if ($config->throttle !== null) {
                        $lastExecution = $task->getLastExecutionTime() ?? 0;
                        if ($lastExecution > 0 && $now - $lastExecution < $config->throttle) {
                            $keepForTiming = true;
                        }
                    } elseif ($config->debounce !== null) {
                        $enqueueTime = $task->getDebounceTime() ?? 0;
                        if ($enqueueTime > 0 && $now - $enqueueTime < $config->debounce) {
                            $keepForTiming = true;
                        }
                    }
                }

                if (!$keepForTiming) {
                    $finished[] = $task;
                }
                continue;
            }

            $this->currentTask = $task;

            // Execute with priority-based step budget
            $budget = $config !== null ? $config->priority->value : 1;

            // CRITICAL: Don't check isFinished() in loop condition - it triggers generator execution
            // Check after each run() instead
            for ($step = 0; $step < $budget; $step++) {
                $yielded = $task->run();

                // Update throttle state immediately after execution
                // This must happen BEFORE checking isFinished so that timing info is available
                if ($config !== null && $config->throttle !== null) {
                    $task->setLastExecutionTime($now);
                }

                // Check if finished after running
                if ($task->isFinished()) {
                    // For throttled/debounced tasks, keep them around until period elapses
                    $keepForTiming = false;
                    if ($config !== null) {
                        if ($config->throttle !== null) {
                            $lastExecution = $task->getLastExecutionTime() ?? 0;
                            if ($lastExecution > 0 && $now - $lastExecution < $config->throttle) {
                                $keepForTiming = true;
                            }
                        } elseif ($config->debounce !== null) {
                            $enqueueTime = $task->getDebounceTime() ?? 0;
                            if ($enqueueTime > 0 && $now - $enqueueTime < $config->debounce) {
                                $keepForTiming = true;
                            }
                        }
                    }

                    if (!$keepForTiming) {
                        $finished[] = $task;
                    }
                    break;
                }
                if ($yielded instanceof Call) {
                    // Track last added task before Call
                    $lastBefore = $this->lastAddedTask;

                    $yielded($task, $this);

                    // If Call enqueued a new task, add it to current tick iteration
                    if ($this->lastAddedTask !== $lastBefore && $this->lastAddedTask !== null) {
                        $newTask = $this->lastAddedTask;
                        if (!isset($processedInThisTick[$newTask])) {
                            $tasks[] = $newTask;
                        }
                    }

                    // If Call paused this task, stop executing it
                    if ($task->isPaused()) {
                        break;
                    }
                }
                // Regular yielded values are not stored - async tasks don't provide sync return values
            }
        }

        foreach ($finished as $task) {
            $this->cancel($task);
        }

        $this->currentTask = null;
        $this->isBusy = false;
    }

    public function pause(Task $task)
    {
        $task->pause();
    }

    public function resume(Task $task)
    {
        $task->resume();
    }

    public function currentTask(): ?Task
    {
        return $this->currentTask;
    }

    public function getLastAddedTask(): Task|null
    {
        return $this->lastAddedTask;
    }

    public function onComplete(Task $task, callable $subscriber)
    {
        if (!$task->isEnqueued()) {
            throw new \RuntimeException('Task is not enqueued');
        }
        $task->addCompletionCallback($subscriber);
    }

    public function getDebounceTime(Task $task): ?float
    {
        return $task->getDebounceTime();
    }

    public function getThrottleTime(Task $task): ?float
    {
        return $task->getLastExecutionTime();
    }
}
