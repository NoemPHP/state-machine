<?php

declare(strict_types=1);

namespace Noem\State\Feature\Interaction;

use Noem\State\Middleware\Mesh;

/**
 * Mesh-based storage for interaction definitions
 *
 * Provides centralized interaction definition registration and lookup through Mesh infrastructure,
 * enabling ChainMail integration and middleware patterns for interaction management.
 *
 * @template-extends Mesh<string, InteractionDefinition>
 */
class InteractionRegistry extends Mesh
{
    /** @var array<string> Track registered interaction IDs for iteration */
    private array $registeredIds = [];

    /** @var array<string, list<string>> Index of state -> interaction IDs */
    private array $stateIndex = [];

    /** @var array<string> Track triggered interaction IDs */
    private array $triggered = [];

    /**
     * Register an interaction definition
     *
     * Stores interaction indexed by id. Overwrites existing interaction with same id.
     *
     * @param InteractionDefinition $definition Interaction to register
     */
    public function register(InteractionDefinition $definition): void
    {
        // Store in Mesh indexed by ID
        $this[$definition->id] = $definition;

        // Track ID for iteration
        if (!in_array($definition->id, $this->registeredIds, true)) {
            $this->registeredIds[] = $definition->id;
        }

        // Index by state for efficient lookup
        if (!isset($this->stateIndex[$definition->state])) {
            $this->stateIndex[$definition->state] = [];
        }
        if (!in_array($definition->id, $this->stateIndex[$definition->state], true)) {
            $this->stateIndex[$definition->state][] = $definition->id;
        }
    }

    /**
     * Retrieve interaction definition by id
     *
     * @param string $id Interaction ID to lookup
     * @return InteractionDefinition|null Definition if found, null otherwise
     */
    public function get(string $id): ?InteractionDefinition
    {
        return $this[$id] ?? null;
    }

    /**
     * Get all interactions for a specific state
     *
     * @param string $state State name to filter by
     * @return array<InteractionDefinition> Interactions associated with state
     */
    public function getByState(string $state): array
    {
        $ids = $this->stateIndex[$state] ?? [];
        $interactions = [];

        foreach ($ids as $id) {
            if (($definition = $this->get($id)) !== null) {
                $interactions[] = $definition;
            }
        }

        return $interactions;
    }

    /**
     * Get all registered interactions
     *
     * @return array<InteractionDefinition> All interaction definitions
     */
    public function all(): array
    {
        $interactions = [];

        foreach ($this->registeredIds as $id) {
            if (($definition = $this->get($id)) !== null) {
                $interactions[] = $definition;
            }
        }

        return $interactions;
    }

    /**
     * Track that an interaction was triggered
     *
     * @param string $id Interaction ID that was triggered
     */
    public function trackTriggered(string $id): void
    {
        // Only track if interaction is registered
        if ($this->get($id) === null) {
            return;
        }

        if (!in_array($id, $this->triggered, true)) {
            $this->triggered[] = $id;
        }
    }

    /**
     * Get all triggered interaction IDs
     *
     * @return array<string> Array of unique interaction IDs that were triggered
     */
    public function getTriggered(): array
    {
        return $this->triggered;
    }
}
