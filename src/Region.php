<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Chains\Params;
use Noem\State\Chains\Params\Action;
use Throwable;

class Region
{
    private string $currentState;

    /**
     * @var object[]
     */
    private array $dispatched = [];

    /**
     * Tracks whether the initial state's onEnter callback has been fired
     */
    private bool $initialStateEntered = false;


    public function __construct(
        private readonly Events                $events,
        string                                 $initial,
        private readonly string                $final,
        private readonly Chains\DispatchAction $actionChain,
        private readonly Chains\DoTransition   $transitionChain,
        private readonly Chains\Path           $path,
    )
    {
        $this->currentState = $initial;
        /**
         * Register a self-destructing one-shot middleware.
         * It takes care of ensuring onEnter is called on the first dispatch in
         * the Region's lifetime.
         */
        $onFirstDispatch = $this->actionChain->link(
            function (Action $action, callable $next) use(&$onFirstDispatch){
                $this->events->onEnterState($this, $this->currentState, $action->payload);
                $onFirstDispatch();
                return $next($action);
            }
        );
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
     */
    private function doDispatch(): void
    {
        /**
         * Copy array and clear the source. This prevents infinite loops
         */
        $dispatched = [...$this->dispatched];
        $this->dispatched = [];
        foreach ($dispatched as $trigger) {
            $context = new Params\Action($this, (object)$trigger);
            $newState = ($this->actionChain)->call($context);
            /**
             * The action has produced a new state.
             * Carry out the transition
             */
            if ($newState !== $this->currentState) {
                $previousState = $this->currentState;
                $this->currentState = $newState;
                $transition = new Params\Transition(
                    $this,
                    (object)$trigger,
                    $previousState
                );
                $result = $this->transitionChain->call($transition);
                //TODO we may want to update the state only when we get true here?
            }
        }
    }

    /**
     * TODO should this not rather be a chain obtained from the builder chainmail?
     * @return string
     */
    public function path(): string
    {
        return $this->path->call($this);
    }

    /**
     * TODO This must go. Regions should not have a concept of parenting as part of their API
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
