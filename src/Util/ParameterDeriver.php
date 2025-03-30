<?php

declare(strict_types=1);

namespace Noem\State\Util;

use ReflectionException;

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
     * @param callable|array $callable $callable
     *   The callable for which we want the parameter type.
     * @param int $param
     *
     * @return string
     *   The class the parameter is type hinted on.
     */
    public static function getParameterType(callable|array $callable, int $param = 0): string
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
                throw new \InvalidArgumentException("No type hint defined for parameter {$param}.");
            }
            $type = $rType->getName();
        } catch (ReflectionException $e) {
            throw new \RuntimeException('Type error registering callable.', 0, $e);
        }

        return $type;
    }

    /**
     * Checks if the specified parameter of a callable is nullable.
     *
     * @param callable|array $callable The callable for which we want to check parameter nullability.
     * @param int $param The index of the parameter to check. Defaults to 0.
     *
     * @return bool Returns true if the parameter is nullable, false otherwise.
     */
    public static function isParameterNullable(callable|array $callable, int $param = 0): bool
    {
        try {
            $reflect = self::reflect($callable);
            $params = $reflect->getParameters();

            if (!isset($params[$param])) {
                throw new \InvalidArgumentException("Required Parameter {$param} not declared.");
            }

            $rType = $params[$param]->getType();

            // If the parameter has no type or is a scalar (which cannot be null), return false
            if (!$rType || $rType->isBuiltin()) {
                return false;
            }

            // Check if the type allows nulls
            return $rType->allowsNull();
        } catch (\ReflectionException $e) {
            throw new \InvalidArgumentException('Not a recognized type of callable', 0, $e);
        }
    }

    /**
     * Returns the number of parameters
     *
     * @param $callable
     *
     * @return int
     * @throws ReflectionException
     */
    public static function getParameterCount($callable): int
    {
        $reflect = self::reflect($callable);
        $params = $reflect->getParameters();

        return count($params);
    }

    /**
     * @throws ReflectionException
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
        int $param = 0
    ): bool {
        $parameterType = self::getParameterType($callback, $param);

        if ($parameterType !== 'object' && !$payload instanceof $parameterType) {
            return false;
        }

        return true;
    }

    /**
     * @throws ReflectionException
     */
    public static function areSignaturesIdentical(callable $callable1, callable $callable2): bool
    {
        // Get reflection objects for both callables
        $reflection1 = self::reflect($callable1);
        $reflection2 = self::reflect($callable2);

        // Check if the number of parameters is the same
        $parameters1 = $reflection1->getParameters();
        $parameters2 = $reflection2->getParameters();

        if (count($parameters1) !== count($parameters2)) {
            return false;
        }

        // Compare each parameter
        foreach ($parameters1 as $index => $param1) {
            $param2 = $parameters2[$index];

            // Check if the types are the same
            if ($param1->getType() !== $param2->getType()) {
                return false;
            }

            // Check if the names are the same (if available)
            if ($param1->getName() !== $param2->getName()) {
                return false;
            }
        }

        // Check if the reflection object has a return type
        $return1 = $reflection1->getReturnType();
        $return2 = $reflection2->getReturnType();

        if (is_null($return1) && is_null($return2)) {
            return false;
        }
        if ($return1 && (string)$return1 !== (string)$return2) {
            return false;
        }

        return true;
    }

    /**
     * @param $callable
     *
     * @return \ReflectionFunction|\ReflectionMethod
     * @throws ReflectionException
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
     * the callable type hint, but it would pass `is_callable()`. Because PHP.
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
