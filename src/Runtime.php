<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Exception\MaxIterationsException;

/**
 * Abstract base class for state machine runtime execution
 *
 * Provides event loop, iteration management, trigger generation, and lifecycle callbacks.
 * Concrete implementations must provide createDefaultTrigger() method.
 */
abstract class Runtime implements \IteratorAggregate
{
    private int $iteration = 0;
    private bool $complete = false;
    private bool $completionFired = false;

    public function __construct(
        private readonly Region $region,
        private readonly RuntimeConfig $config = new RuntimeConfig(),
    ) {
    }

    /**
     * Execute the region's state machine
     *
     * @param int $steps Number of iterations to execute (0 = run to completion)
     * @return bool False if complete, true if still running
     * @throws MaxIterationsException
     */
    public function run(int $steps = 0): bool
    {
        // Register this runtime
        RuntimeRegistry::register($this->region, $this);

        try {
            if ($steps === 0) {
                // Blocking mode - run to completion
                while (!$this->complete) {
                    $wasFinal = $this->region->isFinal();
                    $this->executeIteration();
                    $nowFinal = $this->region->isFinal();

                    // If was final and still final after iteration, we're done
                    if ($wasFinal && $nowFinal) {
                        break;
                    }
                }
                $this->checkCompletion();
                return false;
            } else {
                // Non-blocking mode - execute N steps
                for ($i = 0; $i < $steps; $i++) {
                    if ($this->complete) {
                        break;
                    }

                    $wasFinal = $this->region->isFinal();
                    $this->executeIteration();
                    $nowFinal = $this->region->isFinal();

                    // If was final and still final after iteration, we're done
                    if ($wasFinal && $nowFinal) {
                        break;
                    }
                }
                $this->checkCompletion();
                return !$this->complete;
            }
        } catch (\Throwable $e) {
            // Unregister on exception
            RuntimeRegistry::unregister($this->region);
            throw $e;
        }
    }

    /**
     * Stream events from region execution
     *
     * @return \Generator<object>
     */
    public function events(): \Generator
    {
        $buffer = [];
        $deregister = $this->region->on(function($event) use (&$buffer) {
            $buffer[] = $event;
        });

        try {
            while (!$this->isComplete()) {
                // Execute one iteration
                $this->run(steps: 1);

                // Yield buffered events
                foreach ($buffer as $event) {
                    yield $event;
                }
                $buffer = []; // Clear buffer

                if ($this->isComplete()) {
                    break;
                }
            }
        } finally {
            $deregister(); // Clean up subscription
        }
    }

    /**
     * Check if runtime has completed execution
     */
    public function isComplete(): bool
    {
        return $this->complete || $this->region->isFinal();
    }

    /**
     * Get the region being executed
     */
    public function getRegion(): Region
    {
        return $this->region;
    }

    /**
     * Get the runtime configuration
     */
    public function getConfig(): RuntimeConfig
    {
        return $this->config;
    }

    /**
     * Spawn a child runtime with the same type as this runtime
     *
     * @param Region $region Child region to execute
     * @param RuntimeConfig|null $config Optional config (uses default if not provided)
     * @return static
     */
    public function spawn(Region $region, ?RuntimeConfig $config = null): static
    {
        return new static($region, $config ?? new RuntimeConfig());
    }

    /**
     * Get iterator for event streaming
     */
    public function getIterator(): \Generator
    {
        return $this->events();
    }

    /**
     * Create default trigger for this iteration
     *
     * @param int $iteration Current iteration number
     * @return object Trigger object to pass to region
     */
    abstract protected function createDefaultTrigger(int $iteration): object;

    /**
     * Execute a single iteration
     */
    private function executeIteration(): void
    {
        // Check max iterations (skip check if maxIterations is 0 or -1 for unlimited execution)
        if ($this->config->maxIterations > 0 && $this->iteration >= $this->config->maxIterations) {
            throw new MaxIterationsException(
                "maximum iterations ({$this->config->maxIterations}) exceeded"
            );
        }

        // Generate trigger
        $trigger = $this->config->triggerFactory
            ? ($this->config->triggerFactory)($this->iteration, $this->region)
            : $this->createDefaultTrigger($this->iteration);

        // Fire onIteration callback
        if ($this->config->onIteration) {
            ($this->config->onIteration)($this->region, $trigger, $this->iteration);
        }

        // Execute region with trigger
        $this->region->trigger($trigger);

        $this->iteration++;

        // Check if we've reached final state
        if ($this->region->isFinal()) {
            $this->complete = true;
        }
    }

    /**
     * Check and fire completion callback if needed
     */
    private function checkCompletion(): void
    {
        if ($this->isComplete() && !$this->completionFired) {
            if ($this->config->onComplete) {
                ($this->config->onComplete)();
            }
            $this->completionFired = true;

            // Unregister after completion
            RuntimeRegistry::unregister($this->region);
        }
    }
}
