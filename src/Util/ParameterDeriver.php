<?php

declare(strict_types=1);

namespace Noem\State\Util;

use Noem\State\After;
use Noem\State\Event;
use Noem\State\Hook;
use Noem\State\Name;
use ReflectionParameter;
use ReflectionType;

/**
 * Class ParameterDeriver
 *
 * The ParameterDeriver class provides utility methods to inspect and validate callable parameters.
 * It supports various types of callables, including functions, closures, object methods,
 * static methods, and invokable objects. This class is particularly useful in scenarios where
 * dynamic function or method invocation is required, such as event handling, callback execution,
 * or dependency injection.
 *
 * @package NoNamespace
 */
class ParameterDeriver
{
    /**
     * Derives the class type of the first argument of a callable.
     *
     * @param array|callable $callable $callable
     *   The callable for which we want the parameter type.
     * @param int $param
     *
     * @return string
     *   The class the parameter is type hinted on.
     * @throws \ReflectionException
     */
    public static function getParameterType($callable, int $param = 0): string
    {
        // We can't type hint $callable as it could be an array, and arrays are not callable. Sometimes. Bah, PHP.

        // This try-catch is only here to keep OCD linters happy about uncaught reflection exceptions.
        try {
            $reflect = self::reflect($callable);
            $params = $reflect->getParameters();
            if (!isset($params[$param])) {
                throw new \InvalidArgumentException("Required Parameter {$param} not declared.");
            }
            $rType = $params[$param]->getType();
            if ($rType === null) {
                throw new \InvalidArgumentException('Listeners must typehint their first parameter.');
            }
            $type = $rType->getName();
        } catch (\ReflectionException $e) {
            throw $e;
            throw new \RuntimeException('Type error registering callable.', 0, $e);
        }

        return $type;
    }

    /**
     * @throws \ReflectionException
     */
    public static function getReturnType($callable): string|null
    {
        $returns = self::reflect($callable)->getReturnType();

        if (!$returns) {
            return null;
        }
        assert($returns instanceof \ReflectionNamedType);

        return $returns->getName();
    }

    /**
     * Retrieves the event name associated with a specified parameter of a callable.
     *
     * This method inspects the given callable to determine if the specified parameter
     * is of type `Event` or a subclass thereof. If such a parameter exists and has
     * an associated `Name` attribute, this method returns the event name from that attribute.
     *
     * @param array|callable $callable $callable The callable to inspect.
     * @param int $param The index of the parameter to check (default is 0).
     *
     * @return string|null The event name if found, otherwise null.
     */
    protected static function getEventName($callable, int $param = 0): ?string
    {
        try {
            $reflect = self::reflect($callable);
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
        } catch (\ReflectionException $e) {
            throw new \RuntimeException('Type error registering callable.', 0, $e);
        }

        return null;
    }

    /**
     * Checks if the given payload is compatible with the specified parameter of a callable.
     *
     * This method determines if the type of the provided payload matches the expected
     * parameter type of the callable at the specified position. If the parameter type
     * is 'object', it checks if the payload is an instance of that object type.
     *
     * @param callable $callback
     *   The callable for which to check the parameter compatibility.
     * @param object $payload
     *   The payload object to be checked against the parameter type.
     * @param int $param
     *   (Optional) The index of the parameter to check. Defaults to 0.
     *
     * @return bool
     *   Returns true if the payload is compatible with the parameter type, false otherwise.
     */
    public static function isCompatibleParameter(
        callable $callback,
        object $payload,
        int $param = 0,
        bool $processHooks = true
    ): bool {
        $parameterType = self::getParameterType($callback, $param);

        if ($parameterType !== 'object' && !$payload instanceof $parameterType) {
            return false;
        }

        /**
         * Catch-all listeners do not receive before/after events.
         * This is more of an emotional decision than a technical one because it "feels" right not to call
         * a listener 3 times for a single trigger.
         */
        if ($parameterType === 'object' && $payload instanceof Hook) {
            return false;
        }
        if ($processHooks) {
            $reflect = self::reflect($callback);
            $attributes = $reflect->getAttributes();
            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                if ($instance instanceof Hook) {
                    return get_class($payload) === get_class($instance);
                }
            }
        }

        /**
         * Check if a named event is subscribed to via name attribute
         */
        $eventName = self::getEventName($callback);
        if ($eventName !== null && $payload instanceof Event && $payload->name() !== $eventName) {
            return false;
        }

        return true;
    }

    public static function isCompatibleHook(callable $callback, object $payload, int $param = 0): bool
    {
        $reflect = self::reflect($callback);
        $attributes = $reflect->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if ($instance instanceof Hook) {
                return get_class($payload) === get_class($instance);
            }
        }

        return false;
    }

    public static function getHookedParameter(callable $callback, object $payload, int $param = 0): mixed
    {
        $reflect = self::reflect($callback);
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

    /**
     * @param $callable
     *
     * @return \ReflectionFunction|\ReflectionMethod
     * @throws \ReflectionException
     */
    public static function reflect($callable): \ReflectionFunction|\ReflectionMethod
    {
        return match (true) {
            self::isClassCallable($callable) => (new \ReflectionClass($callable[0]))->getMethod($callable[1]),
            self::isFunctionCallable($callable), self::isClosureCallable($callable) => new \ReflectionFunction(
                $callable
            ),
            self::isObjectCallable($callable) => (new \ReflectionObject($callable[0]))->getMethod($callable[1]),
            self::isInvokable($callable) => (new \ReflectionMethod($callable, '__invoke')),
            default => throw new \InvalidArgumentException('Not a recognized type of callable'),
        };
    }

    /**
     * Determines if a callable represents a function.
     *
     * Or at least a reasonable approximation, since a function name may not be defined yet.
     *
     * @param callable $callable
     *
     * @return True if the callable represents a function, false otherwise.
     */
    protected static function isFunctionCallable($callable): bool
    {
        // We can't check for function_exists() because it may be included later by the time it matters.
        return is_string($callable);
    }

    /**
     * Determines if a callable represents a closure/anonymous function.
     *
     * @param callable $callable
     *
     * @return True if the callable represents a closure object, false otherwise.
     */
    protected static function isClosureCallable(callable $callable): bool
    {
        return $callable instanceof \Closure;
    }

    /**
     * Determines if a callable represents a method on an object.
     *
     * @param callable $callable
     *
     * @return True if the callable represents a method object, false otherwise.
     */
    protected static function isObjectCallable(callable $callable): bool
    {
        return is_array($callable) && is_object($callable[0]);
    }

    /**
     * Determines if a callable represents a static class method.
     *
     * The parameter here is untyped so that this method may be called with an
     * array that represents a class name and a non-static method.  The routine
     * to determine the parameter type is identical to a static method, but such
     * an array is still not technically callable.  Omitting the parameter type here
     * allows us to use this method to handle both cases.
     *
     * Note that this method must therefore be the first in the switch statement
     * above, or else subsequent calls will break as the array is not going to satisfy
     * the callable type hint but it would pass `is_callable()`.  Because PHP.
     *
     * @param callable $callable
     *
     * @return True if the callable represents a static method, false otherwise.
     */
    protected static function isClassCallable($callable): bool
    {
        return is_array($callable) && is_string($callable[0]) && class_exists($callable[0]);
    }

    /**
     * Determines if a callable is a class that has __invoke() method
     *
     * @param callable $callable
     *
     * @return True if the callable represents an invokable object, false otherwise.
     */
    private static function isInvokable(callable $callable): bool
    {
        return is_object($callable);
    }
}
