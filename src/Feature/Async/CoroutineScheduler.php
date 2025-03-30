<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

class CoroutineScheduler
{
    /**
     * @var \SplObjectStorage<Task, list<callable>>
     */
    private \SplObjectStorage $queue;

    /**
     * @var \WeakMap<\Generator, Task>
     */
    private \WeakMap $generatorToTaskMap;

    /**
     * @var \WeakMap<Task, mixed>
     */
    private \WeakMap $lastResults;

    private \SplObjectStorage $completionCallbacks;

    private \WeakMap $pausedTasks;

    private ?Task $currentTask = null;

    private Task|null $lastAddedTask = null;

    private bool $isBusy = false;

    public function __construct()
    {
        $this->queue = new \SplObjectStorage();
        $this->generatorToTaskMap = new \WeakMap();
        $this->lastResults = new \WeakMap();
        $this->completionCallbacks = new \SplObjectStorage();
        $this->pausedTasks = new \WeakMap();
    }

    public function enqueue(\Generator $listener): Task
    {
        if ($task = $this->getTaskForCoroutine($listener)) {
            return $task;
        }
        $task = new Task($listener);
        $this->queue[$task] = new \ArrayObject();
        $this->generatorToTaskMap[$listener] = $task;
        $this->lastAddedTask = $task;

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
        foreach ($this->queue[$task] as $completionCallback) {
            $completionCallback();
        }
        if (isset($this->queue[$task])) {
            unset($this->queue[$task]);
        }
        //if (!isset($this->completionCallbacks[$task])) {
        //    return;
        //}
        //foreach ($this->completionCallbacks[$task] as $completionCallback) {
        //    $completionCallback();
        //}
    }

    public function tick(): void
    {
        if ($this->isBusy) {
            return;
        }
        $this->isBusy = true;

        foreach ($this->queue as $task) {
            if ($this->isPaused($task)) {
                continue;
            }
            $this->currentTask = $task;
            $yielded = $task->run();
            if ($yielded instanceof Call) {
                $yielded($task, $this);
            } else {
                $this->lastResults[$task] = $yielded;
            }

            if ($task->isFinished()) {
                $this->cancel($task);
            }
        }
        $this->currentTask = null;
        $this->isBusy = false;
    }

    public function getLastYielded(Task $task): mixed
    {
        if (!isset($this->lastResults[$task])) {
            return null;
        }

        return $this->lastResults[$task];
    }

    public function pause(Task $task)
    {
        $this->pausedTasks[$task] = true;
    }

    public function resume(Task $task)
    {
        unset($this->pausedTasks[$task]);
    }

    private function isPaused(Task $task): bool
    {
        return isset($this->pausedTasks[$task]);
    }

    public function currentTask(): Task
    {
        return (bool)count($this->queue);
    }

    public function getLastAddedTask(): Task|null
    {
        return $this->lastAddedTask;
    }

    public function onComplete(Task $task, callable $subscriber)
    {
        if (!isset($this->queue[$task])) {
            throw new \RuntimeException('Task is not enqueued');
        }
        $this->queue[$task][] = $subscriber;
    }
}
