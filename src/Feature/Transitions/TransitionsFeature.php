<?php

namespace Noem\State\Feature\Transitions;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\DoTransition;
use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Params\Action;
use Noem\State\Chains\Params\Connection;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Events;
use Noem\State\Feature\Feature;
use Noem\State\Feature\Transitions\Chains\Guard;
use Noem\State\Feature\Transitions\Chains\Params;
use Noem\State\Middleware\ChainException;
use Noem\State\Middleware\ChainMail;

/**
 * A default Region only leaves its current state if the DoDispatch chain yields a different state.
 */
class TransitionsFeature implements Feature
{

    /**
     * @throws ChainException
     */
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(
            fn(ConnectedRegions $c, Events $e): DoTransition => new DoTransition($c, $e),
            fn(InvokeCallback $i, PrepareInvokable $p): Guard => new Guard($i, $p),
            fn(): TransitionRegistry => new TransitionRegistry,

        );
        $chainMail->use($this->checkAvailableTransitionsAfterActionDispatch(...));
    }

    /**
     * When an action is dispatched, check if it is relevant for any of the configure transitions
     * @param DispatchAction $dispatchAction
     * @param ConnectedRegions $connectedRegions
     * @param TransitionRegistry $transitionRegistry
     * @param Chains\Guard $guardChain
     * @return void
     */
    private function checkAvailableTransitionsAfterActionDispatch(
        DispatchAction     $dispatchAction,
        ConnectedRegions   $connectedRegions,
        TransitionRegistry $transitionRegistry,
        Chains\Guard       $guardChain
    )
    {
        $dispatchAction->link(function (Action $action, callable $next) use ($transitionRegistry, $guardChain, $connectedRegions) {
            $currentState = $action->currentState;
            $newState = $next($action);
            /**
             * If an imperative transition occurs, we will not interfere.
             * "imperative" means a new state is explicitly chosen as opposed to what we are doing in here:
             *
             */
            if ($currentState !== $newState) {
                return $newState;
            }
            /**
             * If the machine has already reached a final state, we will not attempt
             * transitions.
             */
            if ($action->region->isFinal()) {
                return $action->currentState;
            }
            $connections = $connectedRegions->call(
                new Connection($action->region)
            );

            /**
             * We cannot transition away before all connected regions have finished
             * //TODO Are there connection types that should not behave like this?
             */
            if (array_any($connections, fn($region) => !$region->isFinal())) {
                return $action->currentState;
            }

            $availableTransitions = $transitionRegistry->getTransitionsForState($action->region, $currentState);
            foreach ($availableTransitions as $target => $guards) {
                foreach ($guards as $guard) {
                    /**
                     * Execute the middleware chain to determine whether a transition is enabled
                     */
                    $context = new Params\Guard($action->region, $action->currentState, $target, $guard, $action->payload);
                    $enabled = $guardChain->call($context);

                    if (!$enabled) {
                        continue;
                    }
                    return $target;
                }
            }
            return $currentState;
        });
    }
}