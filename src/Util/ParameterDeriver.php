<?php

declare(strict_types=1);

namespace Noem\State\Util;

use ReflectionParameter;
use ReflectionType;

class ParameterDeriver
{

    /**
     * Derives the class type of the first argument of a callable.
     *
     * @param callable $callable
     *   The callable for which we want the parameter type.
     *
     * @return string
     *   The class the parameter is type hinted on.
     */
    public static function getParameterType($callable, int $param = 0): string
    {
        // We can't type hint $callable as it could be an array, and arrays are not callable. Sometimes. Bah, PHP.

        // This try-catch is only here to keep OCD linters happy about uncaught reflection exceptions.
        try {
            [$params, $returns] = self::reflect($callable);
            if (!isset($params[$param])) {
                throw new \InvalidArgumentException("Required Parameter {$param} not declared.");
            }
            $rType = $params[$param]->getType();
            if ($rType === null) {
                throw new \InvalidArgumentException('Listeners must declare an object type they can accept.');
            }
            $type = $rType->getName();
        } catch (\ReflectionException $e) {
            throw new \RuntimeException('Type error registering listener.', 0, $e);
        }

        return $type;
    }

    public static function getReturnType($callable): string|null
    {
        [$params, $returns] = self::reflect($callable);
        if (!$returns) {
            return null;
        }
        assert($returns instanceof \ReflectionNamedType);
        return $returns->getName();
    }

    /**
     * @param $callable
     *
     * @return array{list<ReflectionParameter>,?ReflectionType}
     * @throws \ReflectionException
     */
    protected static function reflect($callable): array
    {
        switch (true) {
            // See note on isClassCallable() for why this must be the first case.
            case self::isClassCallable($callable):
                $method = (new \ReflectionClass($callable[0]))->getMethod($callable[1]);
                $params = $method->getParameters();
                $returns = $method->getReturnType();

                return [$params, $returns];
            case self::isFunctionCallable($callable):
            case self::isClosureCallable($callable):
                $reflect = new \ReflectionFunction($callable);
                $params = $reflect->getParameters();
                $returns = $reflect->getReturnType();

                return [$params, $returns];
            case self::isObjectCallable($callable):
                $method = (new \ReflectionObject($callable[0]))->getMethod($callable[1]);
                $params = $method->getParameters();
                $returns = $method->getReturnType();

                return [$params, $returns];
            case self::isInvokable($callable):
                $method = (new \ReflectionMethod($callable, '__invoke'));
                $params = $method->getParameters();
                $returns = $method->getReturnType();

                return [$params, $returns];
            default:
                throw new \InvalidArgumentException('Not a recognized type of callable');
        }
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
    protected static function isFunctionCallable(callable $callable): bool
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
