<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Callbacks\CallbackRecord;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\DefaultCallbackType;
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
    public function __construct(
        /**
         * Middleware chain responsible for inspecting a handler and returning
         * true if it is compatible with the given trigger/payload
         *
         * @var callable(Callback $ontext):bool $validateCallback
         */
        private readonly ValidateCallback $validateCallback,
        private readonly PrepareInvokable $prepareInvokable,
        private readonly InvokeCallback $invokeCallback,
        private readonly CallbackRegistry $registry,
    ) {
    }

    public static function conjure(): \Closure
    {
        return fn(
            Chains\ValidateCallback $v,
            Chains\PrepareInvokable $p,
            Chains\InvokeCallback $i,
            CallbackRegistry $r
        ): Events => new Events(
            $v,
            $p,
            $i,
            $r
        );
    }

    /**
     * @throws Throwable
     */
    private function doCall(Region $region, string $event, string $state, object $trigger): void
    {
        // Query all callback types for this region/event/state
        $records = $this->registry->query(
            region: $region,
            type: null, // Query all types
            event: $event,
            state: $state
        );

        foreach ($records as $record) {
            $handler = $record->callback;

            /**
             * First we need to inspect the signature of the raw closure.
             * If it does not match the signature of the trigger, we skip it.
             */
            $context = new Callback($region, $handler, $trigger, $event, $state);
            if (!$this->validateCallback->call($context)) {
                continue;
            }
            /**
             * Now we need to prepare the handler for invocation.
             * This might involve binding it to a new object or setting up some state.
             */
            $invokable = $this->prepareInvokable->call($context);
            $context = new Callback($region, $invokable, $trigger, $event, $state);
            /**
             * Finally, we can invoke the handler.
             */
            $this->invokeCallback->call($context);
        }
    }

    private function addHandler(string $event, Region $region, string $state, \Closure $handler): self
    {
        $record = new CallbackRecord(
            region: $region,
            type: DefaultCallbackType::get(),
            event: $event,
            state: $state,
            callback: $handler,
            metadata: null
        );

        $this->registry->register($record);

        return $this;
    }

    public function addActionHandler(Region $region, string $state, \Closure $handler): self
    {
        return $this->addHandler('action', $region, $state, $handler);
    }

    public function addEnterStateHandler(Region $region, string $state, \Closure $handler): self
    {
        return $this->addHandler('enter', $region, $state, $handler);
    }

    public function addExitStateHandler(Region $region, string $state, \Closure $handler): self
    {
        return $this->addHandler('exit', $region, $state, $handler);
    }

    /**
     * Handles the transition into a new state
     *
     * This function queries the CallbackRegistry for entry handlers for the specified state
     * and invokes them with the trigger object. It checks the compatibility of the trigger
     * parameter with each entry handler and, if compatible, calls the entry handler.
     * Any exceptions thrown during the call are handled by the extended state object.
     *
     * @param Region $region The region or context within which the action is executed
     * @param string $state The name of the new state to enter
     * @param object $trigger The event or action that triggered the state transition
     *
     * @throws Throwable
     */
    public function onEnterState(Region $region, string $state, object $trigger): void
    {
        $this->doCall($region, 'enter', $state, $trigger);
    }

    /**
     * Handles an action within a state
     *
     * This function queries the CallbackRegistry for action handlers for the specified state
     * and invokes them with the trigger object. It verifies the compatibility of the trigger
     * parameter with each action handler, and if compatible, invokes it.
     * Any exceptions thrown during the execution are managed according to the extended
     * state's exception handling strategy.
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
        $this->doCall($region, 'action', $state, $trigger);
    }

    /**
     * Handles the transition out of a state
     *
     * This function queries the CallbackRegistry for exit handlers for the specified state
     * and invokes them with the trigger object. It checks the compatibility of the trigger
     * parameter with each exit handler and, if compatible, calls the exit handler.
     * Any exceptions thrown during the call are handled by the extended state object.
     *
     * @param Region $region The region or context within which the action is executed
     * @param string $state The name of the state being exited
     * @param object $trigger The event or action that triggered the state transition
     *
     * @throws Throwable
     */
    public function onExitState(Region $region, string $state, object $trigger): void
    {
        $this->doCall($region, 'exit', $state, $trigger);
    }
}
