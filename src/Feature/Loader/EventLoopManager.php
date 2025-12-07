<?php

declare(strict_types=1);

namespace Noem\State\Feature\Loader;

use Noem\State\Region;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * EventLoopManager provides external control over the state machine event loop.
 * This solves the problem of needing to construct the event loop from outside
 * while maintaining flexibility and testability.
 */
class EventLoopManager
{
    private Region $region;
    private ?ContainerInterface $container;
    private array $config;
    private bool $running = false;
    private bool $paused = false;
    private int $iteration = 0;
    private mixed $lastResult = null;
    private ?EventDispatcherInterface $eventDispatcher = null;

    /**
     * Event types that can be dispatched
     */
    public const EVENT_BEFORE_TRIGGER = 'state_machine.before_trigger';
    public const EVENT_AFTER_TRIGGER = 'state_machine.after_trigger';
    public const EVENT_STATE_CHANGED = 'state_machine.state_changed';
    public const EVENT_LOOP_STARTED = 'state_machine.loop_started';
    public const EVENT_LOOP_STOPPED = 'state_machine.loop_stopped';
    public const EVENT_LOOP_PAUSED = 'state_machine.loop_paused';
    public const EVENT_LOOP_RESUMED = 'state_machine.loop_resumed';
    public const EVENT_ERROR = 'state_machine.error';

    public function __construct(
        Region $region,
        ?ContainerInterface $container = null,
        array $config = []
    ) {
        $this->region = $region;
        $this->container = $container;
        $this->config = array_merge([
            'maxIterations' => 10000,
            'tickDelay' => 0, // Milliseconds between iterations
            'trigger' => fn() => new \stdClass(),
            'stopWhen' => null,
            'onIteration' => null,
            'onStateChange' => null,
            'onError' => null,
            'onComplete' => null,
        ], $config);

        // Try to get event dispatcher from container if available
        if ($container && $container->has(EventDispatcherInterface::class)) {
            $this->eventDispatcher = $container->get(EventDispatcherInterface::class);
        }
    }

    /**
     * Create from YAML configuration
     */
    public static function fromYaml(string $yaml, array $options = []): self
    {
        $result = Holon::fromYaml($yaml, array_merge($options, [
            'autoRun' => false, // Never auto-run when creating manager
        ]));

        if ($result instanceof Region) {
            $container = $options['container'] ?? null;
            $config = $options['eventLoop'] ?? [];

            return new self($result, $container, $config);
        }

        throw new \RuntimeException("Failed to create Region from YAML");
    }

    /**
     * Run the event loop synchronously until completion
     */
    public function run(): mixed
    {
        if ($this->running) {
            throw new \RuntimeException("Event loop is already running");
        }

        $this->running = true;
        $this->dispatchEvent(self::EVENT_LOOP_STARTED);

        try {
            while ($this->shouldContinue()) {
                if ($this->paused) {
                    usleep(100000); // Sleep 100ms while paused
                    continue;
                }

                $this->tick();

                if ($this->config['tickDelay'] > 0) {
                    usleep($this->config['tickDelay'] * 1000);
                }
            }

            $this->complete();
        } catch (\Throwable $e) {
            $this->handleError($e);
            throw $e;
        } finally {
            $this->running = false;
            $this->dispatchEvent(self::EVENT_LOOP_STOPPED);
        }

        return $this->lastResult;
    }

    /**
     * Run the event loop asynchronously (requires async feature)
     */
    public function runAsync(): \Generator
    {
        if ($this->running) {
            throw new \RuntimeException("Event loop is already running");
        }

        $this->running = true;
        $this->dispatchEvent(self::EVENT_LOOP_STARTED);

        try {
            while ($this->shouldContinue()) {
                if ($this->paused) {
                    yield; // Yield control while paused
                    continue;
                }

                $this->tick();
                yield $this->lastResult; // Yield result after each tick

                if ($this->config['tickDelay'] > 0) {
                    // In async context, this could be a proper async sleep
                    yield new \Fiber(fn() => usleep($this->config['tickDelay'] * 1000));
                }
            }

            $this->complete();
        } catch (\Throwable $e) {
            $this->handleError($e);
            throw $e;
        } finally {
            $this->running = false;
            $this->dispatchEvent(self::EVENT_LOOP_STOPPED);
        }

        return $this->lastResult;
    }

    /**
     * Execute a single iteration of the event loop
     */
    public function tick(): mixed
    {
        $trigger = $this->createTrigger();

        $this->dispatchEvent(self::EVENT_BEFORE_TRIGGER, ['trigger' => $trigger]);

        $previousState = $this->region->getCurrentState();
        $this->lastResult = $this->region->trigger($trigger);
        $currentState = $this->region->getCurrentState();

        $this->dispatchEvent(self::EVENT_AFTER_TRIGGER, [
            'trigger' => $trigger,
            'result' => $this->lastResult,
        ]);

        if ($previousState !== $currentState) {
            $this->dispatchEvent(self::EVENT_STATE_CHANGED, [
                'previousState' => $previousState,
                'currentState' => $currentState,
            ]);

            if ($this->config['onStateChange']) {
                call_user_func(
                    $this->config['onStateChange'],
                    $previousState,
                    $currentState,
                    $this->region,
                    $this->container
                );
            }
        }

        if ($this->config['onIteration']) {
            call_user_func(
                $this->config['onIteration'],
                $this->region,
                $trigger,
                $this->iteration,
                $this->lastResult
            );
        }

        $this->iteration++;

        return $this->lastResult;
    }

    /**
     * Pause the event loop
     */
    public function pause(): void
    {
        if (!$this->running) {
            throw new \RuntimeException("Event loop is not running");
        }

        $this->paused = true;
        $this->dispatchEvent(self::EVENT_LOOP_PAUSED);
    }

    /**
     * Resume the event loop
     */
    public function resume(): void
    {
        if (!$this->running) {
            throw new \RuntimeException("Event loop is not running");
        }

        $this->paused = false;
        $this->dispatchEvent(self::EVENT_LOOP_RESUMED);
    }

    /**
     * Stop the event loop
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Check if the loop should continue
     */
    private function shouldContinue(): bool
    {
        if (!$this->running) {
            return false;
        }

        if ($this->region->isFinal()) {
            return false;
        }

        if ($this->iteration >= $this->config['maxIterations']) {
            return false;
        }

        if ($this->config['stopWhen'] && is_callable($this->config['stopWhen'])) {
            if (
                call_user_func(
                    $this->config['stopWhen'],
                    $this->region,
                    $this->iteration,
                    $this->lastResult,
                    $this->container
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Create a trigger for the current iteration
     */
    private function createTrigger(): object
    {
        $triggerFactory = $this->config['trigger'];

        if (is_callable($triggerFactory)) {
            return $triggerFactory($this->iteration, $this->region, $this->container);
        }

        return $triggerFactory;
    }

    /**
     * Handle completion of the event loop
     */
    private function complete(): void
    {
        if ($this->config['onComplete'] && is_callable($this->config['onComplete'])) {
            call_user_func(
                $this->config['onComplete'],
                $this->region,
                $this->container,
                $this->lastResult,
                $this->iteration
            );
        }
    }

    /**
     * Handle errors during event loop execution
     */
    private function handleError(\Throwable $error): void
    {
        $this->dispatchEvent(self::EVENT_ERROR, ['error' => $error]);

        if ($this->config['onError'] && is_callable($this->config['onError'])) {
            call_user_func(
                $this->config['onError'],
                $error,
                $this->region,
                $this->container,
                $this->iteration
            );
        }
    }

    /**
     * Dispatch an event if dispatcher is available
     */
    private function dispatchEvent(string $eventName, array $data = []): void
    {
        if (!$this->eventDispatcher) {
            return;
        }

        $event = new class ($eventName, $data) {
            public function __construct(
                public string $name,
                public array $data
            ) {
            }
        };

        $this->eventDispatcher->dispatch($event, $eventName);
    }

    // Getters for monitoring

    public function getRegion(): Region
    {
        return $this->region;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function getIteration(): int
    {
        return $this->iteration;
    }

    public function getLastResult(): mixed
    {
        return $this->lastResult;
    }

    public function getCurrentState(): string
    {
        return $this->region->getCurrentState();
    }

    public function isFinal(): bool
    {
        return $this->region->isFinal();
    }
}
