<?php

declare(strict_types=1);

namespace Noem\State;

class RegionBuilder
{

    private array $states = [];

    private array $regions = [];

    private array $transitions = [];

    private array $cascadingContext = [];

    private array $stateContext = [];

    private array $regionContext = [];

    private ?string $initial;

    private ?string $final;

    public function __construct()
    {
        $this->events = new Events();
    }

    /**
     * Sets an array as list of available states
     *
     * @param array $states An array containing all possible states
     *
     * @return self This builder instance, allowing chaining
     */
    public function setStates(array $states): self
    {
        $this->states = $states;

        return $this;
    }

    /**
     * Adds a specific region for a particular state
     *
     * @param string $state The name of the state
     * @param Region $region The region object to be added
     *
     * @return self This builder instance, allowing chaining
     */
    public function addRegion(string $state, Region $region): self
    {
        $this->regions[$state][] = $region;

        return $this;
    }

    /**
     * Pushes a transition from one state to another based on provided guard condition.
     *
     * @param string $from State that triggers this transition
     * @param string $to Target state after successful transition
     * @param \Closure $guard Guard callback returning true or false
     *
     * @return self This builder instance, allowing chaining
     */
    public function pushTransition(string $from, string $to, \Closure $guard): self
    {
        $this->transitions[$from][$to] = $guard;

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

    /**
     * Builds a new `Region` object using configured settings
     *
     * @return Region Newly built Region instance
     */
    public function build(): Region
    {
        return new Region(
            states: $this->states,
            regions: $this->regions,
            transitions: $this->transitions,
            events: $this->events,
            stateContext: $this->stateContext,
            regionContext: $this->regionContext,
            cascadingContext: $this->cascadingContext,
            initial: $this->initial ?? current($this->states),
            final: $this->final ?? end($this->states)
        );
    }
}
