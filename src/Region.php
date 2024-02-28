<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Util\ParameterDeriver;

class Region
{

    private string $currentState;

    public function __construct(
        private readonly array $states,
        private readonly array $regions,
        private readonly array $transitions,
        private readonly Events $events,
        private array $stateContext,
        private array $regionContext,
        private readonly array $cascadingContext,
        string $initial,
        private string $final
    ) {
        $this->currentState = $initial ?? current($this->states);
    }

    /**
     * Triggers an action on this region and its sub-regions.
     *
     * @param object $payload Payload containing data related to the triggered action
     *
     * @return object Returns the modified payload after processing by all regions involved
     */
    public function trigger(object $payload): object
    {
        $regionStack = new \SplStack();
        $regionStack->push($this);
        $extendedState = new ExtendedState($regionStack);

        return $this->processTrigger($payload, $extendedState, $regionStack);
    }

    /**
     * Carries out all actions relevant to the current trigger while maintaining a stack of nested regions
     *
     * @param object $payload
     * @param ExtendedState $extendedState
     * @param \SplStack $regionStack
     *
     * @return object
     */
    private function processTrigger(object $payload, ExtendedState $extendedState, \SplStack $regionStack): object
    {
        foreach ($regions = $this->regions() as $region) {
            $regionStack->push($region);
            $region->processTrigger($payload, $extendedState, $regionStack);
            $regionStack->pop();
        }
        $this->events->onAction($this->currentState, $payload, $extendedState);
        /**
         * We cannot transition away before all regions have finished
         */
        foreach ($regions as $region) {
            if (!$region->isFinal()) {
                return $payload;
            }
        }

        if (isset($this->transitions[$this->currentState])) {
            foreach ($this->transitions[$this->currentState] as $target => $guard) {
                if (!ParameterDeriver::isCompatibleCallback($guard, $payload)) {
                    continue;
                }
                if ($guard($payload)) {
                    $this->doTransition($target, $extendedState);
                    break;
                }
            }
        }

        return $payload;
    }

    /**
     * Retrieves a list of regions associated with the current state.
     *
     * @return Region[] Array of current regions
     */
    private function regions(): array
    {
        if (!isset($this->regions[$this->currentState])) {
            return [];
        }

        return $this->regions[$this->currentState];
    }

    /**
     * Transition to another state based on the defined transitions.
     *
     * @param string $to Target state to transition to
     * @param ExtendedState $extendedState
     *
     * @return void
     */
    private function doTransition(string $to, ExtendedState $extendedState): void
    {
        $this->events->onExitState($this->currentState, $extendedState);
        $this->currentState = $to;
        $this->events->onEnterState($to, $extendedState);
    }

    /**
     * Checks whether a given key is marked as inheritable across multiple regions.
     *
     * @param string $key Key to check
     *
     * @return bool True if it's an inherited key; false otherwise
     */
    public function inherits(string $key): bool
    {
        return in_array($key, $this->cascadingContext);
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
     * Gets the value mapped under `$key` from the region context.
     *
     * @param string $key Key to look up
     *
     * @return mixed Returns the stored value corresponding to the requested key or null if not found
     */
    public function getRegionContext(string $key): mixed
    {
        return $this->regionContext[$key] ?? null;
    }
    /**
     * Sets the value for the given `$key` in the region context.
     * Throws exception when trying to set an inherited key.
     *
     * @param string $key Key to save the value under
     * @param mixed $value Value to assign
     */
    public function setRegionContext(string $key, mixed $value): void
    {
        if ($this->inherits($key)) {
            throw new \RuntimeException("Cannot set key '{$key}': It is flagged as inherited");
        }
        $this->regionContext[$key] = $value;
    }
    /**
     * Gets the value mapped under `$key` from the state context.
     *
     * @param string $key Key to look up
     *
     * @return mixed Returns the stored value corresponding to the requested key or null if not found
     */
    public function getStateContext(string $key): mixed
    {
        return $this->stateContext[$this->currentState][$key] ?? null;
    }

    /**
     * Sets the value for the given `$key` in the state context.
     *
     * @param string $key Key to save the value under
     * @param mixed $value Value to assign
     */
    public function setStateContext(string $key, mixed $value): void
    {
        $this->stateContext[$this->currentState][$key] = $value;
    }
}
