<?php

declare(strict_types=1);

namespace Noem\State\Middleware;

use Noem\State\Util\ParameterDeriver;
use TypeError;

/**
 * ChainMail is a middleware container that manages dependencies and bootstrapping processes.
 * It uses a Mesh to store and resolve dependencies, and a Chain to manage the boot sequence.
 */
class ChainMail
{
    /**
     * @var array Stores dependency providers indexed by their return types.
     */
    private array $dependencies = [];

    /**
     * @var Mesh Manages the resolution of dependencies using a memoization strategy.
     */
    private Mesh $services;

    /**
     * @var Chain Manages the boot sequence with middleware linking.
     */
    private Chain $boot;

    private bool $booted = false;

    /**
     * Constructs a new ChainMail instance, initializing the services and boot chain.
     */
    public function __construct()
    {
        $cache = [];
        $this->services = new Mesh(
            data: $this->dependencies,
            offsetGet: function (string $offset) use (&$cache) {
                if (!isset($this->dependencies[$offset])) {
                    throw new ChainException("Service '{$offset}' not found");
                }
                if (!isset($cache[$offset])) {
                    $cache[$offset] = $this->invoke($this->dependencies[$offset]);
                }

                return $cache[$offset];
            }
        );
        $this->boot = new Chain();
    }

    /**
     * Boots the ChainMail instance by memoizing services and executing the boot chain.
     *
     * @return self The current ChainMail instance for method chaining.
     */
    public function boot(): self
    {
        if ($this->booted === true) {
            return $this;
        }
        $this->boot->call(null);
        $this->booted = true;

        return $this;
    }

    /**
     * Adds a middleware callback to the boot chain.
     * If already booted, if resolves the callback immediately
     *
     * @param callable $callback The middleware callback to add.
     *
     * @return self The current ChainMail instance for method chaining.
     * @throws ChainException
     */
    public function use(callable $callback): self
    {
        if (!$this->booted) {
            $this->boot->link(function ($nothing, callable $next) use ($callback) {
                $next($nothing);
                $this->invoke($callback);
            });

            return $this;
        }
        $this->invoke($callback);

        return $this;
    }

    /**
     * @template T
     * @param class-string<T> $className
     *
     * @return T
     */
    public function get(string $className): mixed
    {
        return $this->services[$className];
    }

    /**
     * Supplies one or more dependency providers to the ChainMail instance.
     *
     * @param callable ...$dependencies The dependency providers to supply.
     *
     * @return self The current ChainMail instance for method chaining.
     * @throws ChainException
     */
    public function supply(callable ...$dependencies): self
    {
        foreach ($dependencies as $dep) {
            try {
                $type = ParameterDeriver::getReturnType($dep);
            } catch (\ReflectionException $e) {
                throw new ChainException('Failed reflection on callable', 0, $e);
            }
            if (is_null($type)) {
                throw new TypeError("Dependency providers MUST specify a return type");
            }
            if (!$this->isOverlay($dep)) {
                $this->dependencies[$type] = $dep;
                continue;
            }
            $this->services->extend(offsetGet: function (
                string $offset,
                callable $next
            ) use (
                $type,
                $dep
            ) {
                if ($type !== $offset) {
                    return $next($offset);
                }

                return $dep(fn() => $next($offset));
            });
        }

        return $this;
    }

    /**
     * Checks if a callable is an overlay.
     *
     * @param callable $factoryOrOverlay The callable to check.
     *
     * @return bool True if the callable is an overlay, false otherwise.
     */
    private function isOverlay(callable $factoryOrOverlay): bool
    {
        try {
            $reflect = ParameterDeriver::reflect($factoryOrOverlay);
        } catch (\ReflectionException $e) {
            return false;
        }
        $attributes = $reflect->getAttributes();
        foreach ($attributes as $attribute) {
            $instance = $attribute->newInstance();
            if (
                $instance instanceof Overlay
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Invokes a callable with resolved dependencies.
     *
     * @param callable $callable The callable to invoke.
     *
     * @return mixed The result of the invoked callable.
     * @throws ChainException
     */
    public function invoke(callable $callable): mixed
    {
        try {
            $parameters = ParameterDeriver::getParameterCount($callable);
        } catch (\ReflectionException $e) {
            throw new ChainException('Failed reflection on callable', 0, $e);
        }
        $collectorArgs = [];
        for ($i = 0; $i < $parameters; $i++) {
            $type = ParameterDeriver::getParameterType($callable, $i);
            $collectorArgs[] = $this->patch($type, ParameterDeriver::isParameterNullable($callable, $i));
        }

        return $callable(...$collectorArgs);
    }

    /**
     * Retrieves a dependency by key, optionally allowing null values.
     *
     * @param string $key The key of the dependency to retrieve.
     * @param bool $nullable Whether to allow null values.
     *
     * @return mixed The resolved dependency or null if nullable is true and the dependency is not found.
     * @throws ChainException
     */
    protected function patch(string $key, bool $nullable = false): mixed
    {
        try {
            return $this->services[$key];
        } catch (ChainException $exception) {
            if (!$nullable) {
                throw $exception;
            }

            return null;
        }
    }
}
