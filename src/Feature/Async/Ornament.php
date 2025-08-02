<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async;

use Noem\State\Middleware\Mesh;

/**
 * This class extends the functionality of a Mesh by adding asynchronous resolution capabilities.
 *
 * It allows for the definition of a resolver function that computes a value based on the current state of the mesh.
 * The resolved value is cached and only recomputed when its dependencies change, optimizing performance.
 *
 * @template TKey
 * @template TValue
 */
class Ornament
{

    private Mesh $mesh;

    private array $dependencies = [];

    private mixed $resolvedValue = null;

    private bool $isResolved = false;

    private bool $isResolving = false; // New flag

    private string $key;

    /**
     * @var callable
     */
    private $resolver;

    public function __construct(Mesh &$mesh, string $key, callable $resolver)
    {
        $this->mesh = $mesh;
        $this->key = $key;
        $this->resolver = $resolver;
        $this->extendMesh();
    }

    /**
     * Extends the mesh with middleware functions to track and resolve dependencies.
     *
     * This method adds custom behavior to handle offsetGet, offsetSet, and offsetUnset operations,
     * allowing for asynchronous resolution of the defined key.
     */
    private function extendMesh(): void
    {
        //$this->mesh->memoize($this->shouldMemoize(...));
        $this->mesh->extend(
            $this->trackOffsetExists(...),
            $this->trackOffsetGet(...),
            $this->trackOffsetSet(...),
            $this->trackOffsetUnset(...)
        );
    }
    /**
     * Middleware function to track offsetExists operations on the mesh.
     *
     * If the accessed offset matches the defined key, it checks if the resolved value is available.
     * Otherwise, it delegates to the next middleware in the chain.
     *
     * @param mixed $offset The offset being checked for existence.
     * @param callable $next The next middleware in the chain.
     *
     * @return bool True if the offset exists or has been resolved; otherwise, false.
     */
    public function trackOffsetExists(mixed $offset, callable $next): bool
    {
        if ($offset !== $this->key) {
            return $next($offset);
        }
        if ($this->isResolved) {
            return true;
        }
        if (!$this->isResolving) {
            $this->startResolution();
        }

        return $next($offset);
    }

    /**
     * Middleware function to track offsetGet operations on the mesh.
     *
     * If the accessed offset matches the defined key, it triggers the resolution process.
     * Otherwise, it tracks dependencies and delegates to the next middleware in the chain.
     *
     * @param mixed $offset The offset being accessed.
     * @param callable $next The next middleware in the chain.
     *
     * @return mixed The resolved value or the result of the next middleware.
     */
    public function trackOffsetGet(mixed $offset, callable $next): mixed
    {
        if ($offset !== $this->key) {
            if ($this->isResolving) {
                $this->dependencies[$offset] = true;
            }

            return $next($offset);
        }
        /**
         * Check if the property has already been resolved
         */
        if ($this->isResolved) {
            return $this->resolvedValue;
        }

        $this->startResolution();
        /**
         * Check again - maybe we resolved synchronously.
         * Then we can immediately return results!
         */
        if ($this->isResolved) {
            return $this->resolvedValue;
        }

        return $next($offset);
    }

    /**
     * Middleware function to track offsetSet operations on the mesh.
     *
     * If the set offset is a dependency, it resets the resolution state to ensure recomputation.
     * Otherwise, it delegates to the next middleware in the chain.
     *
     * @param object $context The context containing the offset and value being set.
     * @param callable $next The next middleware in the chain.
     */
    public function trackOffsetSet(object $context, callable $next): void
    {
        if (isset($this->dependencies[$context->offset])) {
            $this->reset();
        }
        $next($context);
    }

    /**
     * Middleware function to track offsetUnset operations on the mesh.
     *
     * If the unset offset is a dependency, it resets the resolution state to ensure recomputation.
     * Otherwise, it delegates to the next middleware in the chain.
     *
     * @param mixed $offset The offset being unset.
     * @param callable $next The next middleware in the chain.
     */
    public function trackOffsetUnset($offset, callable $next): void
    {
        if (isset($this->dependencies[$offset])) {
            $this->reset();
        }
        $next($offset);
    }

    protected function startResolution(): void
    {
        if (!$this->isResolving) {
            $this->isResolving = true; // Set resolving flag to true

            $resolve = function (mixed $result) {
                $this->resolvedValue = $result;
                $this->isResolved = true;
                $this->isResolving = false; // Reset resolving flag after resolution
            };
            ($this->resolver)(new OrnamentResolver($this->mesh, $resolve));
        }
    }

    /**
     * Resets the resolution state, clearing cached values and dependencies.
     *
     * This method is used to force recomputation of the resolved value on subsequent accesses.
     */
    public function reset(): void
    {
        $this->isResolved = false;
        $this->isResolving = false; // Reset resolving flag on reset
        $this->resolvedValue = null;
    }
}
