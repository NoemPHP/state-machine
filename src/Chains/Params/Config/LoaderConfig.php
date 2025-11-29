<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params\Config;

/**
 * Typed accessor for loader configuration.
 * 
 * Provides convenient access to common loader data patterns used across features.
 * This is a shared accessor that any feature can use for standard loader operations.
 */
class LoaderConfig extends ConfigAccessor
{
    /**
     * Get array-based loader data (from YAML/array input).
     * 
     * @return array|null Full loader array or null if not set
     */
    public function array(): ?array
    {
        return $this->get('loader.array');
    }

    /**
     * Check if array-based loader is being used.
     * 
     * @return bool True if loader.array exists
     */
    public function hasArray(): bool
    {
        return $this->has('loader.array');
    }

    /**
     * Get states array from loader config.
     * 
     * @return array<string> List of state names, empty array if not defined
     */
    public function states(): array
    {
        return $this->get('loader.array.states', []);
    }

    /**
     * Check if states are defined in loader config.
     * 
     * @return bool
     */
    public function hasStates(): bool
    {
        return $this->has('loader.array.states');
    }

    /**
     * Get context section from loader, optionally a specific key within context.
     * 
     * @param string|null $key Optional key within context (e.g., 'resolvers')
     * @param mixed $default Default value if not found
     * @return mixed Context data or specific key value
     * 
     * @example
     * ```php
     * // Get full context
     * $context = $loader->context();
     * 
     * // Get specific key
     * $resolvers = $loader->context('resolvers', []);
     * ```
     */
    public function context(?string $key = null, mixed $default = null): mixed
    {
        $path = $key 
            ? "loader.array.context.{$key}"
            : 'loader.array.context';
        return $this->get($path, $default);
    }

    /**
     * Check if context section exists, optionally for a specific key.
     * 
     * @param string|null $key Optional key within context
     * @return bool
     */
    public function hasContext(?string $key = null): bool
    {
        $path = $key 
            ? "loader.array.context.{$key}"
            : 'loader.array.context';
        return $this->has($path);
    }

    /**
     * Get loader config metadata (loader-specific configuration).
     * 
     * @return array|null
     */
    public function loaderConfig(): ?array
    {
        return $this->get('loader.loaderConfig');
    }

    /**
     * Check if a specific loader type exists.
     * 
     * @param string $type Type key under loader (e.g., 'array', 'loaderConfig')
     * @return bool
     */
    public function hasType(string $type): bool
    {
        return $this->has("loader.{$type}");
    }

    /**
     * Get data for a specific loader type.
     * 
     * @param string $type Type key under loader
     * @param mixed $default Default if type not found
     * @return mixed
     */
    public function type(string $type, mixed $default = null): mixed
    {
        return $this->get("loader.{$type}", $default);
    }
}
