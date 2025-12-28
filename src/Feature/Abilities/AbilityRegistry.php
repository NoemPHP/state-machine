<?php

declare(strict_types=1);

namespace Noem\State\Feature\Abilities;

use Noem\State\Middleware\Mesh;

/**
 * Mesh-based storage for ability definitions
 *
 * Provides centralized ability registration and lookup through Mesh infrastructure,
 * enabling ChainMail integration and middleware patterns for ability management.
 *
 * @template-extends Mesh<string, AbilityDefinition>
 */
class AbilityRegistry extends Mesh
{
    /** @var array<string> Track registered ability names for iteration */
    private array $registeredNames = [];

    /**
     * Register an ability definition
     *
     * Stores ability indexed by name. Overwrites existing ability with same name.
     *
     * @param AbilityDefinition $definition Ability to register
     */
    public function register(AbilityDefinition $definition): void
    {
        $this[$definition->name] = $definition;
        if (!in_array($definition->name, $this->registeredNames, true)) {
            $this->registeredNames[] = $definition->name;
        }
    }

    /**
     * Retrieve ability definition by name
     *
     * @param string $name Ability name to lookup
     * @return AbilityDefinition|null Definition if found, null otherwise
     */
    public function get(string $name): ?AbilityDefinition
    {
        return $this[$name] ?? null;
    }

    /**
     * Get all registered abilities
     *
     * @return array<string, AbilityDefinition> All ability definitions indexed by name
     */
    public function all(): array
    {
        // Use tracked names to retrieve abilities
        // Cannot use Mesh iterator because it uses numeric indexing
        // but we store with string keys
        $abilities = [];
        foreach ($this->registeredNames as $name) {
            if (($ability = $this->get($name)) !== null) {
                $abilities[$name] = $ability;
            }
        }

        return $abilities;
    }
}
