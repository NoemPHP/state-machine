<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Observer\ActionObserver;
use Noem\State\Observer\EnterStateObserver;
use Noem\State\Observer\ExitStateObserver;
use Noem\State\Util\ParameterDeriver;

class EventManager implements EnterStateObserver, ExitStateObserver, ActionObserver
{
    /**
     * @var callable[][]
     */
    private array $actionHandlers = [];

    private array $entryHandlers = [];

    private array $exitHandlers = [];

    public function onAction(StateInterface $state, object $payload, ObservableStateMachineInterface $machine)
    {
        if (!isset($this->actionHandlers[(string)$state])) {
            return;
        }

        array_walk(
            $this->actionHandlers[(string)$state],
            function (callable $handler) use ($payload, $state) {
                $parameterType = ParameterDeriver::getParameterType($handler);
                if (!$payload instanceof $parameterType) {
                    return;
                }
                $handler($payload, $state);
            }
        );
    }

    /**
     * @param string|StateInterface $state
     * @param callable(object):object $handler
     *
     * @return $this
     */
    public function addActionHandler(string|StateInterface $state, callable $handler): self
    {
        $this->actionHandlers[(string)$state][] = $handler;

        return $this;
    }

    public function onEnterState(StateInterface $state, ObservableStateMachineInterface $machine)
    {
        if (!isset($this->entryHandlers[(string)$state])) {
            return;
        }

        array_walk(
            $this->entryHandlers[(string)$state],
            function (callable $handler) use ($state) {
                $handler($state);
            }
        );
    }

    public function addEnterStateHandler(string|StateInterface $state, callable $handler): self
    {
        $this->entryHandlers[(string)$state][] = $handler;

        return $this;
    }

    public function onExitState(StateInterface $state, ObservableStateMachineInterface $machine)
    {
        if (!isset($this->exitHandlers[(string)$state])) {
            return;
        }

        array_walk(
            $this->exitHandlers[(string)$state],
            function (callable $handler) use ($state) {
                $handler($state);
            }
        );
    }

    public function addExitStateHandler(string|StateInterface $state, callable $handler): self
    {
        $this->exitHandlers[(string)$state][] = $handler;

        return $this;
    }
}
