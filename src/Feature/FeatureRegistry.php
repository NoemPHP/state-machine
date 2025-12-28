<?php

declare(strict_types=1);

namespace Noem\State\Feature;

use Noem\State\Middleware\ChainMail;

/**
 * Manages feature registration, dependency resolution, and topological sorting.
 *
 * Features are registered by class name to prevent duplicates. During resolution,
 * the registry recursively discovers dependencies via RequiresFeature attributes,
 * auto-instantiates missing features, and performs topological sort to ensure
 * dependencies are invoked before dependents.
 */
class FeatureRegistry
{
    /**
     * @var array<class-string, Feature>
     */
    private array $features = [];

    /**
     * @var \SplObjectStorage<ChainMail, array<Feature>>
     */
    private \SplObjectStorage $resolved;

    public function __construct()
    {
        $this->resolved = new \SplObjectStorage();
    }

    /**
     * Register a feature instance by its class name.
     *
     * Duplicate registrations of the same class are ignored.
     * Clears all per-ChainMail resolution caches to force re-resolution on next resolve() call.
     *
     * @param Feature $feature
     */
    public function register(Feature $feature): void
    {
        $className = $feature::class;

        if (!isset($this->features[$className])) {
            $this->features[$className] = $feature;
            // Clear all caches when new features are registered
            $this->resolved = new \SplObjectStorage();
        }
    }

    /**
     * Check if a feature class is already registered.
     *
     * @param class-string $featureClass
     * @return bool
     */
    public function isRegistered(string $featureClass): bool
    {
        return isset($this->features[$featureClass]);
    }

    /**
     * Resolve all dependencies recursively, invoke features, and return features in topological order.
     *
     * This method:
     * 1. Checks if features have already been resolved and invoked for this ChainMail instance
     * 2. If cached, returns the cached sorted array without re-invoking
     * 3. If not cached:
     *    a. Discovers all transitive dependencies via RequiresFeature attributes
     *    b. Auto-instantiates missing dependencies using zero-arg constructors
     *    c. Builds a dependency graph
     *    d. Performs topological sort to determine invocation order
     *    e. Invokes all features with the provided ChainMail instance
     *    f. Caches the result for this ChainMail instance
     * 4. Detects and rejects circular dependencies
     *
     * Features are invoked exactly once per ChainMail instance. Subsequent calls with the
     * same instance return the cached array without re-invoking features, preventing
     * duplicate middleware registration when builders share ChainMail via newInstance().
     *
     * @param ChainMail $chainMail The ChainMail instance to invoke features with
     * @return array<Feature> Features in dependency order (dependencies first)
     * @throws \LogicException If circular dependencies detected
     */
    public function resolve(ChainMail $chainMail): array
    {
        // Return cached result if available for this ChainMail instance
        if ($this->resolved->offsetExists($chainMail)) {
            return $this->resolved[$chainMail];
        }

        // 1. Recursively collect all dependencies
        $this->collectDependencies();

        // 2. Build dependency graph
        $graph = $this->buildGraph();

        // 3. Topological sort
        $sorted = $this->topologicalSort($graph);

        // 4. Invoke all features with the ChainMail instance
        foreach ($sorted as $feature) {
            $feature($chainMail);
        }

        // Cache the result for this ChainMail instance
        // Note: We store the array directly. Each ChainMail instance gets the same
        // feature instances but PHP's array copy-on-write ensures modifications
        // won't affect other caches.
        $this->resolved[$chainMail] = $sorted;

        return $sorted;
    }

    /**
     * Recursively discover and auto-instantiate all missing dependencies.
     */
    private function collectDependencies(): void
    {
        $toProcess = $this->features;

        while (!empty($toProcess)) {
            $feature = array_shift($toProcess);
            $dependencies = $this->getDependencies($feature);

            foreach ($dependencies as $depClass) {
                if (!$this->isRegistered($depClass)) {
                    // Auto-instantiate missing dependency
                    $depFeature = new $depClass();
                    $this->register($depFeature);
                    $toProcess[] = $depFeature; // Process its dependencies too
                }
            }
        }
    }

    /**
     * Extract dependency class names from RequiresFeature attributes.
     *
     * @param Feature $feature
     * @return array<class-string>
     */
    private function getDependencies(Feature $feature): array
    {
        $reflection = new \ReflectionClass($feature);
        $attributes = $reflection->getAttributes(RequiresFeature::class);

        $dependencies = [];
        foreach ($attributes as $attr) {
            $instance = $attr->newInstance();
            $dependencies[] = $instance->featureFQCN;
        }

        return $dependencies;
    }

    /**
     * Build adjacency list representation of dependency graph.
     *
     * @return array<class-string, array{feature: Feature, deps: array<class-string>}>
     */
    private function buildGraph(): array
    {
        $graph = [];

        foreach ($this->features as $className => $feature) {
            $graph[$className] = [
                'feature' => $feature,
                'deps' => $this->getDependencies($feature),
            ];
        }

        return $graph;
    }

    /**
     * Perform topological sort using Kahn's algorithm.
     *
     * @param array<class-string, array{feature: Feature, deps: array<class-string>}> $graph
     * @return array<Feature> Features in dependency order
     * @throws \LogicException If circular dependencies detected
     */
    private function topologicalSort(array $graph): array
    {
        // Calculate in-degrees (number of incoming edges)
        $inDegree = [];
        $adjList = [];

        // Initialize
        foreach ($graph as $node => $data) {
            $inDegree[$node] = 0;
            $adjList[$node] = $data['deps'];
        }

        // Calculate in-degrees
        foreach ($adjList as $node => $deps) {
            foreach ($deps as $dep) {
                if (!isset($inDegree[$dep])) {
                    $inDegree[$dep] = 0;
                }
                $inDegree[$dep]++;
            }
        }

        // Queue nodes with no incoming edges (no dependencies)
        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        // Process queue
        $sorted = [];
        while (!empty($queue)) {
            $node = array_shift($queue);
            $sorted[] = $graph[$node]['feature'];

            // Reduce in-degree for dependent nodes
            foreach ($adjList[$node] as $dep) {
                $inDegree[$dep]--;
                if ($inDegree[$dep] === 0) {
                    $queue[] = $dep;
                }
            }
        }

        // Check for cycles
        if (count($sorted) !== count($graph)) {
            throw new \LogicException('Circular feature dependencies detected');
        }

        // Reverse to get dependencies-first order
        return array_reverse($sorted);
    }
}
