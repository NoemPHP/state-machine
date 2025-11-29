<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Chains\Params\Config\ConfigAccessor;
use Noem\State\Middleware\Mesh;
use Noem\State\RegionBuilder;

class BuildParams extends Mesh
{
    private array $accessorCache = [];

    public function __construct(
        public readonly RegionBuilder $builder,
        private ?iterable &$params = [],
    ) {
        parent::__construct($params);
    }

    /**
     * Get a typed config accessor for fluent, type-safe configuration access.
     *
     * Accessors are cached per BuildParams instance for performance.
     *
     * @template T of ConfigAccessor
     * @param class-string<T> $accessorClass The accessor class to instantiate
     * @return T The cached or newly created accessor instance
     *
     * @example
     * ```php
     * $loaderConfig = $context->config(LoaderConfig::class);
     * $states = $loaderConfig->states();
     * ```
     */
    public function config(string $accessorClass): ConfigAccessor
    {
        if (!isset($this->accessorCache[$accessorClass])) {
            $this->accessorCache[$accessorClass] = new $accessorClass($this);
        }
        return $this->accessorCache[$accessorClass];
    }

    /**
     * Get value at dot-notation path with optional default.
     *
     * This is a generic infrastructure method. For feature-specific config,
     * prefer creating a typed ConfigAccessor subclass.
     *
     * @param string $path Dot-notation path like 'loader.array.context.resolvers'
     * @param mixed $default Default value if path doesn't exist
     * @return mixed Value at path or default
     *
     * @example
     * ```php
     * $resolvers = $context->getPath('loader.array.context.resolvers', []);
     * ```
     */
    public function getPath(string $path, mixed $default = null): mixed
    {
        $current = $this->params;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) && !$current instanceof \ArrayAccess) {
                return $default;
            }
            if (!isset($current[$segment])) {
                return $default;
            }
            $current = $current[$segment];
        }
        return $current;
    }

    /**
     * Check if dot-notation path exists in configuration.
     *
     * @param string $path Dot-notation path
     * @return bool True if path exists, false otherwise
     */
    public function hasPath(string $path): bool
    {
        $current = $this->params;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) && !$current instanceof \ArrayAccess) {
                return false;
            }
            if (!isset($current[$segment])) {
                return false;
            }
            $current = $current[$segment];
        }
        return true;
    }
}
