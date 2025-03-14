<?php

declare(strict_types=1);

namespace Noem\State\Feature\NamedEvents;

use Noem\State\Chains\Params\Callback;
use Noem\State\Chains\Params;
use Noem\State\Chains;
use Noem\State\Chains\ValidateCallback;
use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\Util\ParameterDeriver;
use ReflectionException;

class NamedEvents implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use(function (
            Chains\Guard $transitionMiddleware,
            ValidateCallback $eventMiddleware,
        ): void {
            $transitionMiddleware->link(
                function (Params\Guard $context, callable $next, callable $first): bool {
                    $result = $next($context);
                    if (!$result) {
                        /**
                         * Any matched named event is predicated on a matched event type.
                         * In other words, it does not sound plausible that we can resolve a named event
                         * if $next() === null
                         */
                        return false;
                    }

                    /**
                     * Check if a named event is subscribed to via name attribute
                     */
                    $eventName = $this->getEventName($context->handler);
                    if (
                        $eventName !== null
                        && $context->trigger instanceof Event
                        && $context->trigger->name() !== $eventName
                    ) {
                        return false;
                    }

                    return true;
                }
            );

            $eventMiddleware->link(
                function (Callback $context, callable $next, callable $first): ?callable {
                    $result = $next($context);
                    if (is_null($result)) {
                        /**
                         * Any matched named event is predicated on a matched event type.
                         * In other words, it does not sound plausible that we can resolve a named event
                         * if $next() === null
                         */
                        return $result;
                    }

                    /**
                     * Check if a named event is subscribed to via name attribute
                     */
                    $eventName = $this->getEventName($context->handler);
                    if (
                        $eventName !== null
                        && $context->trigger instanceof Event
                        && $context->trigger->name() !== $eventName
                    ) {
                        return $result;
                    }

                    return null;
                }
            );
        });
    }

    /**
     * Retrieves the event name associated with a specified parameter of a callable.
     *
     * This method inspects the given callable to determine if the specified parameter
     * is of type `Event` or a subclass thereof. If such a parameter exists and has
     * an associated `Name` attribute, this method returns the event name from that attribute.
     *
     * @param callable|array $callable $callable The callable to inspect.
     * @param int $param The index of the parameter to check (default is 0).
     *
     * @return string|null The event name if found, otherwise null.
     * @throws \ReflectionException
     */
    private function getEventName(callable|array $callable, int $param = 0): ?string
    {
        try {
            $reflect = ParameterDeriver::reflect($callable);
            $params = $reflect->getParameters();
            if (!isset($params[$param])) {
                throw new \InvalidArgumentException("Required Parameter {$param} not declared.");
            }
            $parameter = $params[$param];
            // Check if the first parameter is an Event type
            if (!$parameter->getType() instanceof \ReflectionNamedType) {
                throw new \InvalidArgumentException('Listeners must typehint their first parameter.');
            }
            $paramType = $parameter->getType()->getName();

            if ($paramType === Event::class || is_subclass_of($paramType, Event::class)) {
                // Check for the Name attribute
                foreach ($parameter->getAttributes(Name::class) as $attribute) {
                    $nameAttribute = $attribute->newInstance();
                    assert($nameAttribute instanceof Name);

                    return $nameAttribute->eventName;
                }
            }
        } catch (ReflectionException $e) {
            throw new \RuntimeException('Type error registering callable.', 0, $e);
        }

        return null;
    }
}
