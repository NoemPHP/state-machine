<?php

declare(strict_types=1);

namespace Noem\State\Feature\EventHooks;

use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Params\Action;
use Noem\State\Chains\Params\Callback;
use Noem\State\Chains\ValidateCallback;
use Noem\State\Chains\DispatchAction;
use Noem\State\Feature\EventHooks\Hook\After;
use Noem\State\Feature\EventHooks\Hook\Before;
use Noem\State\Feature\EventHooks\Hook\Hook;
use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use Noem\State\Util\ParameterDeriver;

/**
 * This Feature modifies the event handling such that for any dispatched event, two additional events are dispatched:
 * One "Before" event and one "After" event. They can be subscribed to by using function attributes like this:
 *
 * #[Before] function( EventType $event ){  }
 */
class EventHooks implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use(function (
            DispatchAction $dispatchAction,
            ValidateCallback $validateCallback,
            InvokeCallback $invokeCallback,
        ): void {
            /**
             * This is the action middleware that wraps around the actual action execution.
             *   It creates two new events: one "Before" event and one "After" event.
             *   These events are then dispatched before and after the actual action.
             *   For those special events, the middleware is restarted so they pass through the entire chain
             *   The origin event is passed on as usual.
             */
            $dispatchAction->link(function (Action $context, callable $next, callable $first): string {
                if ($context->payload instanceof Hook) {
                    return $next($context);
                }
                /**
                 * Here we create two new events: Before and After.
                 *   They are clones of the original context but with a different payload.
                 */
                $beforeContext = clone $context;
                $beforeContext->payload = Before::fromEvent($context->payload);
                $afterContext = clone $context;
                $afterContext->payload = After::fromEvent($context->payload);

                $resultBefore = $first($beforeContext);
                /**
                 * If this caused a transition, abort
                 */
                if ($resultBefore !== $context->currentState) {
                    return $resultBefore;
                }
                $result = $next($context); // Here we just call $next() instead of $first()
                /**
                 * If this caused a transition, abort
                 */
                if ($result !== $context->currentState) {
                    return $result;
                }
                $first($afterContext);

                return $result;
            });
            /**
             * Now we adjust callback validation: Valid callbacks are dismissed if they carry a non-matching hook
             * Invalid callbacks are enable if they carry a matching hook
             */
            $validateCallback->link(
                function (Callback $context, callable $next, callable $first): bool {
                    $isValid = $next($context);

                    if ($isValid) {
                        /**
                         * If the vanilla code was able to resolve a callback,
                         * we still need to inspect whether it uses the new attributes.
                         * If so, dismiss it.
                         */
                        if ($this->hasHookAttribute($context->handler)) {
                            return false;
                        }

                        return $isValid;
                    }
                    /**
                     * If no vanilla callback was found, check for the new attributes.
                     *   This is where we decide whether to use the new handler or not.
                     */
                    if (!$this->isCompatibleHook($context->handler, $context->trigger)) {
                        return false;
                    }
                    /**
                     * Fetch the origin event object and validate that it is compatible with our handler.
                     */
                    $trigger = $this->getHookedParameter($context->handler, $context->trigger);
                    if (!ParameterDeriver::isCompatibleParameter($context->handler, $trigger)) {
                        return false;
                    }
                    /**
                     * All checks green.
                     * Set the correct payload and move forward
                     */
                    $context->trigger = $trigger;

                    return true;
                }
            );
            /**
             * The Prestigio!
             * Here we actually map the trigger to the callback
             */
            $invokeCallback->link(function (Callback $context, callable $next, callable $first): mixed {
                if (!$this->hasHookAttribute($context->handler)) {
                    return $next($context);
                }
                $trigger = $this->getHookedParameter($context->handler, $context->trigger);

                return ($context->handler)($trigger);
            });
        });
    }

    /**
     * @throws ReflectionException
     */
    private function isCompatibleHook(callable $callback, object $payload, int $param = 0): bool
    {
        $reflect = ParameterDeriver::reflect($callback);
        $attributes = $reflect->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof Hook) {
                return get_class($payload) === get_class($instance);
            }
        }

        return false;
    }

    /**
     * Fetch the origin event object via a matching attribute definition
     *
     * @param callable $callback
     * @param object $payload
     *
     * @return mixed
     * @throws \ReflectionException
     */
    private function getHookedParameter(callable $callback, object $payload): mixed
    {
        $reflect = ParameterDeriver::reflect($callback);
        $attributes = $reflect->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if (
                $instance instanceof Hook
                && get_class($payload) === get_class($instance)
            ) {
                return $payload->event;
            }
        }

        return $payload;
    }

    private function hasHookAttribute(callable $callback): bool
    {
        $reflect = ParameterDeriver::reflect($callback);
        $attributes = $reflect->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if (
                $instance instanceof Hook
            ) {
                return true;
            }
        }

        return false;
    }
}
