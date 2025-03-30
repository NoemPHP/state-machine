<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Chains\Params\Callback;
use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Chains\ValidateCallback;
use Noem\State\Middleware\Chain;
use Noem\State\Util\ParameterDeriver;
use SplObjectStorage;
use Throwable;

class Events
{

    private \SplObjectStorage $onEnter;

    private \SplObjectStorage $onExit;

    private \SplObjectStorage $action;

    public function __construct(
        /**
         * Middleware chain responsible for inspecting a handler and returning
         * true if it is compatible with the given trigger/payload
         *
         * @var callable(Callback $ontext):bool $validateCallback
         */
        private readonly ValidateCallback $validateCallback,
        private readonly PrepareInvokable $prepareInvokable,
        private readonly InvokeCallback $invokeCallback
    ) {
        $this->onEnter = new \SplObjectStorage();
        $this->onExit = new \SplObjectStorage();
        $this->action = new \SplObjectStorage();
    }

    public static function conjure(): \Closure
    {
        return fn(
            Chains\ValidateCallback $v,
            Chains\PrepareInvokable $p,
            Chains\InvokeCallback $i
        ): Events => new Events(
            $v,
            $p,
            $i
        );
    }

    /**
     * @throws Throwable
     */
    private function doCall(Region $region, array $handlers, object $trigger): void
    {
        foreach ($handlers as $handler) {
            /**
             * First we need to inspect the signature of the raw closure.
             * If it does not match the signature of the trigger, we skip it.
             */
            $context = new Callback($region, $handler, $trigger);
            if (!$this->validateCallback->call($context)) {
                continue;
            }
            /**
             * Now we need to prepare the handler for invocation.
             * This might involve binding it to a new object or setting up some state.
             */
            $invokable = $this->prepareInvokable->call($context);
            $context = new Callback($region, $invokable, $trigger);
            /**
             * Finally, we can invoke the handler.
             */
            $this->invokeCallback->call($context);
        }
    }

    /**
     * @param \SplObjectStorage $collection
     * @param Region $region
     * @param string $state
     * @param \Closure $handler
     *
     * @return $this
     */
    private function addHandler(\SplObjectStorage $collection, Region $region, string $state, \Closure $handler): self
    {
        if (!$collection->contains($region)) {
            $collection->attach($region, new \stdClass());
        }
        if (!isset($collection[$region]->$state)) {
            $collection[$region]->$state = [];
        }
        $collection[$region]->$state[] = $handler;

        return $this;
    }

    public function addActionHandler(Region $region, string $state, \Closure $handler): self
    {
        return $this->addHandler($this->action, $region, $state, $handler);
    }

    public function addEnterStateHandler(Region $region, string $state, \Closure $handler): self
    {
        return $this->addHandler($this->onEnter, $region, $state, $handler);
    }

    public function addExitStateHandler(Region $region, string $state, \Closure $handler): self
    {
        return $this->addHandler($this->onExit, $region, $state, $handler);
    }

    /**
     * Handles the transition into a new state
     *
     * This function checks if the entry handler for the specified state exists, and if so, iterates through the list
     * of entry handlers for that state. It checks the compatibility of the trigger parameter with each entry handler
     * and, if compatible, calls the entry handler with the extended state and trigger objects as
     * arguments. Any exceptions thrown during the call are handled by the extended state object.
     *
     * @param Region $region The region or context within which the action is executed
     * @param string $state The name of the new state to enter
     * @param object $trigger The event or action that triggered the state transition
     *
     * @throws Throwable
     */
    public function onEnterState(Region $region, string $state, object $trigger): void
    {
        $handlers = $this->getHandlersForRegionAndState($this->onEnter, $region, $state);
        if ($handlers) {
            $this->doCall($region, $handlers, $trigger);
        }
    }

    /**
     * Handles an action within a state
     *
     * This function checks if the action handler for the specified action exists and iterates through the list of
     * action handlers associated with that action. It verifies the compatibility of the trigger parameter with each
     * action handler, and if compatible, invokes it using the provided extended state and trigger objects.
     * Any exceptions thrown during the execution of an action handler are managed by the corresponding extended
     * state object to ensure robust error handling.
     *
     * @param Region $region The region or context within which the action is executed
     * @param string $state The specific action to be handled
     * @param object $trigger An object representing the event or input that triggered the action
     *
     * @throws Throwable If any error occurs during action execution or if an exception is thrown by an action handler,
     *                   it will be caught and managed according to the extended state's exception handling strategy.
     */
    public function onAction(Region $region, string $state, object $trigger): void
    {
        $handlers = $this->getHandlersForRegionAndState($this->action, $region, $state);
        if ($handlers) {
            $this->doCall($region, $handlers, $trigger);
        }
    }

    /**
     * Handles the transition out of a state
     *
     * This function checks if the exit handler for the specified state exists, and if so, iterates through the list
     * of exit handlers for that state. It checks the compatibility of the trigger parameter with each exit handler
     * and, if compatible, calls the exit handler's `call()` method with the extended state and trigger objects as
     * arguments. Any exceptions thrown during the call are handled by the extended state object.
     *
     * @param Region $region The region or context within which the action is executed
     * @param string $state The name of the state being exited
     * @param object $trigger The event or action that triggered the state transition
     *
     * @throws Throwable
     */
    public function onExitState(Region $region, string $state, object $trigger): void
    {
        $handlers = $this->getHandlersForRegionAndState($this->onExit, $region, $state);
        if ($handlers) {
            $this->doCall($region, $handlers, $trigger);
        }
    }

    /**
     * Retrieves the handlers for a given region and state from an SplObjectStorage instance
     *
     * @param SplObjectStorage $storage The storage containing region-handler associations
     * @param Region $region The specific region to look for
     * @param string $state The state for which to find the associated handlers
     *
     * @return array|null An array of handlers if found, otherwise null
     */
    private function getHandlersForRegionAndState(SplObjectStorage $storage, Region $region, string $state): ?array
    {
        if (!$storage->contains($region)) {
            return null;
        }
        if (!isset($storage[$region]->$state)) {
            return null;
        }

        return $storage[$region]->$state;
    }
}
