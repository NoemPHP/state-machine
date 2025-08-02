<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Chains\Params;
use Noem\State\Chains\Params\Connection;
use Noem\State\Middleware\ChainException;
use ReflectionException;
use Throwable;

class Region
{
    private string $currentState;

    /**
     * @var object[]
     */
    private array $dispatched = [];

    public function __construct(
        private readonly array $states,
        private readonly array $transitions,
        private readonly Events $events,
        string $initial,
        private readonly string $final,
        private readonly Chains\DispatchAction $actionChain,
        private readonly Chains\Guard $guardChain,
        private readonly Chains\ConnectedRegions $connectionsChain,
        private readonly Chains\Path $path,
    ) {
        $this->currentState = $initial;
        $this->actionChain->link(function (Params\Action $context, callable $next): string {
            return $next($context);
        });
        ;
    }

    /**
     * Triggers an action on this region and its sub-regions.
     *
     * @param object $payload Payload containing data related to the triggered action
     * @param bool $enqueue
     *
     * @return object Returns the modified payload after processing by all regions involved
     */
    public function trigger(object $payload, bool $enqueue = false): object
    {
        $this->dispatched[] = $payload;

        !$enqueue && $this->doDispatch();

        return $payload;
    }

    /**
     * Carries out all actions relevant to the current trigger while maintaining a stack of nested regions
     *
     * @param object $payload
     *
     * @return string The state the Region should be in after processing
     * @throws ReflectionException
     * @throws Throwable
     */
    protected function processTrigger(object $payload): string
    {
        /**
         * Process connected regions first.
         * This allows for nested states and transitions.
         */
        foreach ($connections = $this->connections() as $region) {
            $region->processTrigger($payload);
        }

        $this->events->onAction($this, $this->currentState, $payload);
        /**
         * We cannot transition away before all connected regions have finished
         * //TODO Are there connection types that should not behave like this?
         */
        if (array_any($connections, fn($region) => !$region->isFinal())) {
            return $this->currentState;
        }
        /**
         * Transitions are processed in the order they were defined.
         * This means that if multiple transitions have the same trigger, only the first one will be executed.
         * TODO: This should be executed AFTER the Chain has run, not within its provider
         */
        if (isset($this->transitions[$this->currentState])) {
            foreach ($this->transitions[$this->currentState] as $target => $guards) {
                foreach ($guards as $guard) {
                    /**
                     * Execute the middleware chain to determine whether a transition is enabled
                     */
                    $context = new Params\Guard($this, $this->currentState, $target, $guard, $payload);
                    $enabled = $this->guardChain->call($context);

                    if ($enabled) {
                        $this->doTransition($target, $payload);

                        return $target;
                    }
                }
            }
        }

        return $this->currentState;
    }

    /**
     */
    private function doDispatch(): void
    {
        /**
         * Copy array and clear the source. This prevents infinite loops
         */
        $dispatched = [...$this->dispatched];
        $this->dispatched = [];
        $provider = fn(Params\Action $ctx): string => $this->processTrigger($ctx->payload);
        foreach ($dispatched as $trigger) {
            $context = new Params\Action($this, (object)$trigger);
            ($this->actionChain)->withProvider($provider)->call($context);
        }
    }

    /**
     * Retrieves a list of regions associated with the current state.
     *
     * @return Region[] Array of current regions
     */
    private function connections(): array
    {
        return $this->connectionsChain->call(
            new Connection($this)
        );
    }

    public function path(): string
    {
        return $this->path->call($this);
    }

    /**
     * Transition to another state based on the defined transitions.
     *
     * @param string $to Target state to transition to
     * @param object $trigger
     *
     * @return void
     * @throws Throwable
     */
    private function doTransition(
        string $to,
        object $trigger,
    ): void {
        $this->events->onExitState($this, $this->currentState, $trigger);
        $this->currentState = $to;
        foreach ($this->connections() as $region) {
            $region->onEnterParent($trigger);
        }
        $this->events->onEnterState($this, $to, $trigger);
    }

    /**
     * @throws Throwable
     */
    public function onEnterParent(object $trigger): void
    {
        $this->events->onEnterState($this, $this->currentState, $trigger);
        /**
         * If onEnter dispatched anything, we can safely process them right away
         */
        $this->doDispatch();
    }

    public function onDispatch(object $trigger): void
    {
        $this->dispatched[] = $trigger;
    }

    /**
     * Determines if we have reached the end or final state.
     *
     * @return bool True if we are at the final state; false otherwise
     */
    public function isFinal(): bool
    {
        return $this->currentState === $this->final;
    }

    /**
     * Checks if the current state matches the specified one.
     *
     * @param string $state State to compare against
     *
     * @return bool True if the current state matches the provided state; false otherwise
     */
    public function isInState(string $state): bool
    {
        return $this->currentState === $state;
    }

    /**
     * Returns the current state of the region
     *
     * @return string
     */
    public function currentState(): string
    {
        return $this->currentState;
    }
}
