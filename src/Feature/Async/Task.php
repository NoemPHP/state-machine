<?php

namespace Noem\State\Feature\Async;

class Task
{
    protected \Generator $coroutine;

    protected mixed $sendValue = null;

    protected bool $beforeFirstYield = true;

    private ?AsyncConfig $config = null;

    private ?float $debounceEnqueueTime = null;

    private ?float $lastExecutionTime = null;

    /**
     * @var list<callable>
     */
    private array $completionCallbacks = [];

    private bool $isPaused = false;

    private bool $isEnqueued = false;

    private bool $isCancelled = false;

    public function __construct(
        \Generator $coroutine,
        public readonly Priority $priority = Priority::NORMAL
    ) {
        $this->coroutine = $coroutine;
    }

    public function setSendValue($sendValue)
    {
        $this->sendValue = $sendValue;
    }

    public function run()
    {
        if ($this->beforeFirstYield) {
            $this->beforeFirstYield = false;
            $retval = $this->coroutine->current();
        } else {
            $retval = $this->coroutine->send($this->sendValue);
            $this->sendValue = null;
        }

        return $this->isFinished() ? $this->coroutine->getReturn() : $retval;
    }

    public function getReturn(): mixed
    {
        return $this->coroutine->getReturn();
    }

    public function isFinished(): bool
    {
        return !$this->coroutine->valid();
    }

    /**
     * Replace the coroutine with a new one (for debounce/throttle latest payload capture)
     */
    public function replaceCoroutine(\Generator $newCoroutine): void
    {
        $this->coroutine = $newCoroutine;
        $this->beforeFirstYield = true;  // Reset to initial state
        $this->sendValue = null;
    }

    // AsyncConfig management
    public function getConfig(): ?AsyncConfig
    {
        return $this->config;
    }

    public function setConfig(?AsyncConfig $config): void
    {
        $this->config = $config;
    }

    // Debounce timing
    public function getDebounceTime(): ?float
    {
        return $this->debounceEnqueueTime;
    }

    public function setDebounceTime(?float $time): void
    {
        $this->debounceEnqueueTime = $time;
    }

    // Throttle timing
    public function getLastExecutionTime(): ?float
    {
        return $this->lastExecutionTime;
    }

    public function setLastExecutionTime(?float $time): void
    {
        $this->lastExecutionTime = $time;
    }

    // Completion callbacks
    public function addCompletionCallback(callable $callback): void
    {
        $this->completionCallbacks[] = $callback;
    }

    /**
     * Register callback to invoke when task completes
     *
     * If task is already finished, callback is invoked immediately.
     * Otherwise, callback is invoked when task finishes during run().
     *
     * Callback receives task's return value as parameter.
     */
    public function onComplete(callable $callback): void
    {
        if ($this->isFinished()) {
            // Already finished - invoke immediately
            $callback($this->getReturn());
        } else {
            // Store for later
            $this->completionCallbacks[] = $callback;
        }
    }

    public function triggerCompletionCallbacks(): void
    {
        if (!empty($this->completionCallbacks)) {
            $result = $this->isFinished() ? $this->getReturn() : null;
            foreach ($this->completionCallbacks as $callback) {
                $callback($result);
            }
            $this->completionCallbacks = [];
        }
    }

    // Pause state
    public function pause(): void
    {
        $this->isPaused = true;
    }

    public function resume(): void
    {
        $this->isPaused = false;
    }

    public function isPaused(): bool
    {
        return $this->isPaused;
    }

    // Enqueued state
    public function markEnqueued(): void
    {
        $this->isEnqueued = true;
    }

    public function isEnqueued(): bool
    {
        return $this->isEnqueued;
    }

    // Cancelled state
    public function cancel(): void
    {
        $this->isCancelled = true;
        $this->isEnqueued = false; // Task is no longer enqueued when cancelled
    }

    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }
}
