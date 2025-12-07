<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params\Config;

use Noem\State\Chains\Params\BuildParams;

/**
 * Base class for typed configuration accessors.
 *
 * Features can extend this to create domain-specific config access patterns
 * with type safety and IDE autocomplete.
 *
 * The constructor is final to ensure consistent instantiation through BuildParams.
 * If subclasses need initialization logic, they should override initialize().
 *
 * @example
 * ```php
 * class MyFeatureConfig extends ConfigAccessor {
 *     private array $cache = [];
 *
 *     protected function initialize(): void {
 *         $this->cache = $this->buildCache();
 *     }
 *
 *     public function widgets(): array {
 *         return $this->cache['widgets'] ?? [];
 *     }
 * }
 *
 * // Usage in feature:
 * $config = $context->config(MyFeatureConfig::class);
 * $widgets = $config->widgets();
 * ```
 */
abstract class ConfigAccessor
{
    /**
     * Constructor is final to ensure consistent instantiation pattern.
     * Subclasses requiring initialization should override initialize().
     *
     * @param BuildParams $params The build parameters to access config from
     */
    final public function __construct(
        protected readonly BuildParams $params
    ) {
        $this->initialize();
    }

    /**
     * Template method for subclass initialization logic.
     * Called automatically after construction completes.
     *
     * Override this method if your accessor needs to:
     * - Build caches or indexes
     * - Validate configuration
     * - Pre-compute derived values
     *
     * Do NOT override __construct() - it will break the instantiation pattern.
     */
    protected function initialize(): void
    {
        // Default: no initialization needed
    }

    /**
     * Get value at path with optional default.
     *
     * @param string $path Dot-notation path (e.g., 'loader.array.context')
     * @param mixed $default Value to return if path doesn't exist
     * @return mixed
     */
    protected function get(string $path, mixed $default = null): mixed
    {
        return $this->params->getPath($path, $default);
    }

    /**
     * Check if path exists in configuration.
     *
     * @param string $path Dot-notation path
     * @return bool
     */
    protected function has(string $path): bool
    {
        return $this->params->hasPath($path);
    }

    /**
     * Require path to exist or throw exception.
     *
     * @param string $path Dot-notation path
     * @return mixed Value at path
     * @throws \RuntimeException If path doesn't exist
     */
    protected function require(string $path): mixed
    {
        if (!$this->has($path)) {
            throw new \RuntimeException(
                sprintf("Required config missing: %s", $path)
            );
        }
        return $this->get($path);
    }
}
