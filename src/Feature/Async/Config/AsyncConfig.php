<?php

declare(strict_types=1);

namespace Noem\State\Feature\Async\Config;

use Noem\State\Chains\Params\Config\ConfigAccessor;

/**
 * Typed accessor for AsyncFeature configuration.
 *
 * Provides convenient access to async-specific configuration such as
 * resolver definitions from loader config.
 */
class AsyncConfig extends ConfigAccessor
{
    /**
     * Get resolver definitions from loader config.
     *
     * @return array<array{name: string, run: \Closure}> List of resolver definitions
     */
    public function resolvers(): array
    {
        return $this->get('loader.array.context.resolvers', []);
    }

    /**
     * Check if resolvers are defined in loader config.
     *
     * @return bool True if at least one resolver is defined
     */
    public function hasResolvers(): bool
    {
        $resolvers = $this->resolvers();
        return !empty($resolvers);
    }

    /**
     * Get a specific resolver definition by name.
     *
     * @param string $name The resolver name to find
     * @return array{name: string, run: \Closure}|null Resolver definition or null if not found
     */
    public function resolver(string $name): ?array
    {
        foreach ($this->resolvers() as $resolver) {
            if ($resolver['name'] === $name) {
                return $resolver;
            }
        }
        return null;
    }

    /**
     * Check if a specific resolver is defined.
     *
     * @param string $name The resolver name to check
     * @return bool
     */
    public function hasResolver(string $name): bool
    {
        return $this->resolver($name) !== null;
    }
}
