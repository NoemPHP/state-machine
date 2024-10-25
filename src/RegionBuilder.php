<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Util\ParameterDeriver;

class RegionBuilder
{
    protected Events $events;

    protected array $states = [];

    private array $regions = [];

    protected array $transitions = [];

    protected array $cascadingContext = [];

    protected array $stateContext = [];

    protected array $regionContext = [];

    protected ?string $initial;

    protected ?string $final;

    private array $middlewares = [];

    protected \Closure $regionFactory;

    public function __construct()
    {
        $this->events = new Events();

        $this->regionFactory = function (): Region {
            return new Region(
                states: $this->states,
                regions: $this->buildSubRegions(),
                transitions: $this->transitions,
                events: $this->events,
                stateContext: $this->stateContext,
                regionContext: $this->regionContext,
                cascadingContext: $this->cascadingContext,
                initial: $this->initial ?? current($this->states),
                final: $this->final ?? end($this->states)
            );
        };
    }

    public function pushMiddleware(\Closure $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    private function applyMiddlewares(RegionBuilder $regionBuilder): Region
    {
        $creator = fn() => (function () {
            $this->assertValidConfig();

            return ($this->regionFactory)->call($this);
        })->call($regionBuilder);
        foreach ($this->middlewares as $middleware) {
            $creator = function () use ($middleware, $regionBuilder, $creator) {
                return $middleware($regionBuilder, $creator);
            };
        }

        return $creator();
    }

    /**
     * Sets an array as list of available states
     *
     * @param array $states An array containing all possible states
     *
     * @return self This builder instance, allowing chaining
     */
    public function setStates(string ...$states): self
    {
        $this->states = $states;

        return $this;
    }

    public function addState(string $state): self
    {
        $this->states[] = $state;

        return $this;
    }

    public function eachState(\Closure $callback): self
    {
        foreach ($this->states as $state) {
            $callback($state, $this);
        }

        return $this;
    }

    /**
     * Adds a specific region for a particular state
     *
     * @param string $state The name of the state
     * @param RegionBuilder $regionBuilder The region builder to be added
     *
     * @return self This builder instance, allowing chaining
     */
    public function addRegion(string $state, RegionBuilder $regionBuilder): self
    {
        $this->regions[$state][] = $regionBuilder;

        return $this;
    }

    /**
     * Pushes a transition from one state to another based on provided guard condition.
     *
     * @param string $from State that triggers this transition
     * @param string $to Target state after successful transition
     * @param ?\Closure $guard Guard callback returning true or false. Optional, allow by default
     *
     * @return self This builder instance, allowing chaining
     */
    public function pushTransition(string $from, string $to, ?\Closure $guard = null): self
    {
        $this->transitions[$from][$to] = $guard ?? fn(object $t): bool => true;

        return $this;
    }

    /**
     * Specifies keys that should be inherited through multiple regions
     *
     * @param array $keys List of key names to inherit
     *
     * @return self This builder instance, allowing chaining
     */
    public function inherits(array $keys): self
    {
        $this->cascadingContext = $keys;

        return $this;
    }

    /**
     * Registers action event handlers per state
     *
     * @param string $state Name of the state where the handler should apply
     * @param \Closure $callback Action handler callback
     *
     * @return self This builder instance, allowing chaining
     */
    public function onAction(string $state, \Closure $callback): self
    {
        $this->events->addActionHandler($state, $callback);

        return $this;
    }

    public function onEnter(string $state, \Closure $callback): self
    {
        $this->events->addEnterStateHandler($state, $callback);

        return $this;
    }

    public function onExit(string $state, \Closure $callback): self
    {
        $this->events->addExitStateHandler($state, $callback);

        return $this;
    }

    /**
     * Specify context values associated with each state
     *
     * @param string $state Name of the state
     * @param array $context Key-value pairs representing the desired state context
     *
     * @return self This builder instance, allowing chaining
     */
    public function setStateContext(string $state, array $context): self
    {
        $this->stateContext[$state] = $context;

        return $this;
    }

    /**
     * Set initial and default context values for the constructed Region
     *
     * @param array $context Initial/default values for Region context
     *
     * @return self This builder instance, allowing chaining
     */
    public function setRegionContext(array $context): self
    {
        $this->regionContext = $context;

        return $this;
    }

    /**
     * Marks the specified state as the starting point (initial) for a newly created Region
     *
     * @param string $state Starting state name
     *
     * @return self This builder instance, allowing chaining
     */
    public function markInitial(string $state): self
    {
        $this->initial = $state;

        return $this;
    }

    /**
     * Designates the final destination state for a newly generated Region
     *
     * @param string $state Final target state
     *
     * @return self This builder instance, allowing chaining
     */
    public function markFinal(string $state): self
    {
        $this->final = $state;

        return $this;
    }

    public function setFactory(callable $factory): self
    {
        $this->regionFactory = $factory;
        return $this;
    }

    /**
     * Builds a new `Region` object using configured settings
     *
     * @return Region Newly built Region instance
     */
    public function build(): Region
    {
        return $this->applyMiddlewares($this);
    }

    protected function assertValidConfig()
    {
        if (empty($this->states)) {
            throw new \RuntimeException("States cannot be empty");
        }
        if (count($this->states) > 1) {
        }
    }



    protected function buildSubRegions(): array
    {
        $built = [];
        foreach ($this->regions as $state => $regions) {
            $built[$state] = array_map(
                function (RegionBuilder $b) {
                    /**
                     * Pass on current middlewares to sub-region builders
                     */
                    foreach ($this->middlewares as $middleware) {
                        $b->pushMiddleware($middleware);
                    }

                    return $b->build();
                },
                $regions
            );
        }

        return $built;
    }
}
