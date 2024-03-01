<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Util\ParameterDeriver;

class Events
{
    /**
     * @var \Closure[][]
     */
    private array $actionHandlers = [];

    /**
     * @var \Closure[][]
     */
    private array $entryHandlers = [];

    /**
     * @var \Closure[][]
     */
    private array $exitHandlers = [];

    public function onAction(string $state, object $trigger, Context $extendedState)
    {
        if (!isset($this->actionHandlers[$state])) {
            return;
        }

        foreach ($this->actionHandlers[$state] as $actionHandler) {
            if (!ParameterDeriver::isCompatibleParameter($actionHandler, $trigger)) {
                continue;
            }
            try {
                $actionHandler->call($extendedState, $trigger);
            } catch (\Throwable $exception) {
                $extendedState->handleException($exception);
            }
        }
    }

    /**
     * @param string $state
     * @param \Closure $handler
     *
     * @return $this
     */
    public function addActionHandler(string $state, \Closure $handler): self
    {
        $this->actionHandlers[$state][] = $handler;

        return $this;
    }

    /**
     * Handles the transition into a new state
     *
     * This function checks if the entry handler for the specified state exists, and if so, iterates through the list
     * of entry handlers for that state. It checks the compatibility of the trigger parameter with each entry handler
     * and, if compatible, calls the entry handler with the extended state and trigger objects as
     * arguments. Any exceptions thrown during the call are handled by the extended state object.
     *
     * @param string $state The name of the new state to enter
     * @param object $trigger The event or action that triggered the state transition
     * @param Context $extendedState The extended state object containing additional information
     *
     * @throws \Throwable
     */
    public function onEnterState(string $state, object $trigger, Context $extendedState): void
    {
        if (!isset($this->entryHandlers[$state])) {
            return;
        }

        foreach ($this->entryHandlers[$state] as $entryHandler) {
            if (!ParameterDeriver::isCompatibleParameter($entryHandler, $trigger)) {
                continue;
            }
            try {
                $entryHandler->call($extendedState, $trigger);
            } catch (\Throwable $exception) {
                $extendedState->handleException($exception);
            }
        }
    }

    public function addEnterStateHandler(string $state, \Closure $handler): self
    {
        $this->entryHandlers[$state][] = $handler;

        return $this;
    }

    /**
     * Handles the transition out of a state
     * This function checks if the exit handler for the specified state exists, and if so, iterates through the list
     * of exit handlers for that state. It checks the compatibility of the trigger parameter with each exit handler
     * and, if compatible, calls the exit handler's `call()` method with the extended state and trigger objects as
     * arguments. Any exceptions thrown during the call are handled by the extended state object.
     *
     * @param string $state The name of the state being exited
     * @param object $trigger The event or action that triggered the state transition
     * @param Context $extendedState The extended state object containing additional information
     *
     * @throws \Throwable
     */
    public function onExitState(string $state, object $trigger, Context $extendedState): void
    {
        if (!isset($this->exitHandlers[$state])) {
            return;
        }

        foreach ($this->exitHandlers[$state] as $exitHandler) {
            if (!ParameterDeriver::isCompatibleParameter($exitHandler, $trigger)) {
                continue;
            }
            try {
                $exitHandler->call($extendedState, $trigger);
            } catch (\Throwable $exception) {
                $extendedState->handleException($exception);
            }
        }
    }

    public function addExitStateHandler(string $state, \Closure $handler): self
    {
        $this->exitHandlers[$state][] = $handler;

        return $this;
    }
}
