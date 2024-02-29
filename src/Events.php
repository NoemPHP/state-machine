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

    private array $entryHandlers = [];

    private array $exitHandlers = [];

    public function onAction(string $state, object $payload, ExtendedState $extendedState)
    {
        if (!isset($this->actionHandlers[$state])) {
            return;
        }

        foreach ($this->actionHandlers[$state] as $actionHandler) {
            if (!ParameterDeriver::isCompatibleCallback($actionHandler, $payload)) {
                continue;
            }
            $actionHandler->call($extendedState, $payload);
        }
    }

    /**
     * @param string $state
     * @param callable(object):object $handler
     *
     * @return $this
     */
    public function addActionHandler(string $state, \Closure $handler): self
    {
        $this->actionHandlers[$state][] = $handler;

        return $this;
    }

    public function onEnterState(string $state, object $trigger, ExtendedState $extendedState)
    {
        if (!isset($this->entryHandlers[$state])) {
            return;
        }

        foreach ($this->entryHandlers[$state] as $entryHandler) {
            $entryHandler->call($extendedState, $trigger);
        }
    }

    public function addEnterStateHandler(string $state, \Closure $handler): self
    {
        $this->entryHandlers[$state][] = $handler;

        return $this;
    }

    public function onExitState(string $state, object $trigger, ExtendedState $extendedState)
    {
        if (!isset($this->exitHandlers[$state])) {
            return;
        }

        foreach ($this->exitHandlers[$state] as $exitHandler) {
            $exitHandler->call($extendedState, $trigger);
        }
    }

    public function addExitStateHandler(string $state, \Closure $handler): self
    {
        $this->exitHandlers[$state][] = $handler;

        return $this;
    }
}
