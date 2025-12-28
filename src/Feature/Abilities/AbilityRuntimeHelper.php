<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities;

use Noem\State\Region;

/**
 * Runtime helper for ability registration within state callbacks
 *
 * Provides fluent API for registering abilities at runtime via $this->abilities()->register()
 * pattern. Complements build-time RegisterAbility BuildStep with in-callback registration.
 */
final class AbilityRuntimeHelper
{
    public function __construct(
        private readonly Region $region,
        private readonly AbilityRegistry $registry
    ) {
    }

    /**
     * Register an ability at runtime
     *
     * @param string $name Unique ability identifier
     * @param array $config Ability configuration with keys:
     *   - name: string (required, must match first parameter)
     *   - description: string (optional, default: '')
     *   - parameterSchema: array (optional, default: [])
     *   - responseSchema: array (optional, default: [])
     *   - handler: callable (required)
     *   - predicate: callable|null (optional, default: null)
     * @return void
     */
    public function register(string $name, array $config): void
    {
        // Validate required fields
        if (!isset($config['handler'])) {
            throw new \InvalidArgumentException('Handler is required in ability config');
        }

        if (!is_callable($config['handler'])) {
            throw new \InvalidArgumentException('Handler must be callable');
        }

        // Validate name consistency
        if (isset($config['name']) && $config['name'] !== $name) {
            throw new \InvalidArgumentException(
                sprintf('Name mismatch: parameter "%s" does not match config[\'name\'] "%s"', $name, $config['name'])
            );
        }

        // Create definition
        $definition = new AbilityDefinition(
            name: $name,
            description: $config['description'] ?? '',
            parameterSchema: $config['parameterSchema'] ?? [],
            responseSchema: $config['responseSchema'] ?? [],
            handler: $config['handler'],
            predicate: $config['predicate'] ?? null,
        );

        // Register in registry
        $this->registry->register($definition);
    }
}
