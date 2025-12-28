# Runtime Implementation Plan

**Based on**: Runtime.md, Runtime-Chain-Architecture.md, Runtime-Context-Sharing.md
**Date**: 2025-12-13
**Status**: Planning

## Overview

Sequential implementation plan for Runtime abstraction. Each phase builds on the previous, following spec-driven development methodology.

---

## Phase 1: Core Infrastructure

### 1.1 Add Region::getChainMail() Accessor

**Files to modify**:
- `src/Region.php`

**Changes**:
```php
class Region
{
    public function __construct(
        private readonly Events $events,
        string $initial,
        private readonly string $final,
        private readonly Chains\DispatchAction $actionChain,
        private readonly Chains\DoTransition $transitionChain,
        private readonly Chains\Path $path,
        public readonly Chains\Notification $notificationChain,
        private readonly Middleware\ChainMail $chainMail,  // NEW
    ) {
        // ... existing constructor code
    }

    /**
     * Get the ChainMail for this region
     *
     * Allows features and runtime wrappers to access chains.
     * Use with caution - this exposes internal structure.
     *
     * @internal
     */
    public function getChainMail(): Middleware\ChainMail
    {
        return $this->chainMail;
    }
}
```

**Testing**:
- Unit test: Verify getChainMail() returns ChainMail instance
- Integration test: Verify features can access chains via this method

**Dependencies**: RegionBuilder must pass ChainMail to Region constructor

**Estimated complexity**: Low (simple accessor)

---

### 1.2 Add ExtendedState::getMesh() Helper

**Files to modify**:
- `src/Feature/ExtendedState/ExtendedState.php`

**Changes**:
```php
class ExtendedState implements Feature
{
    // ... existing code ...

    /**
     * Get the Mesh for a region, if ExtendedState is loaded
     *
     * Returns null if ExtendedState feature is not loaded or on error.
     *
     * @param Region $region
     * @return Mesh|null
     */
    public static function getMesh(Region $region): ?Mesh
    {
        try {
            $chainMail = $region->getChainMail();
            $metaChain = $chainMail->get(Chains\Meta::class);

            if (!$metaChain) {
                return null;
            }

            $metaParams = new Chains\Params\Meta(
                $region,
                ContextMetaType::get()
            );

            return $metaChain->call($metaParams);
        } catch (\Throwable $e) {
            return null; // ExtendedState not loaded or error
        }
    }
}
```

**Testing**:
- Unit test: getMesh() returns null when ExtendedState not loaded
- Unit test: getMesh() returns Mesh when ExtendedState loaded
- Integration test: Mesh contains expected context data

**Dependencies**:
- Region::getChainMail() (from 1.1)
- ExtendedState feature existing implementation

**Estimated complexity**: Low

---

### 1.3 Create Runtime Interface

**Files to create**:
- `src/Runtime.php`

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Noem\State;

/**
 * Runtime provides execution context for a Region
 *
 * Treats state machines as event streams that can be:
 * - Iterated (foreach)
 * - Piped (Chain composition)
 * - Embedded (dynamic sub-runtimes)
 * - Executed sync or async (callback-based)
 */
interface Runtime extends \IteratorAggregate
{
    /**
     * Execute the Region until completion
     *
     * Does NOT return synchronously. Use onComplete callback.
     * This enables sync/async agnostic execution.
     *
     * @param callable|null $onComplete fn(mixed $result): void
     * @return void
     */
    public function run(?callable $onComplete = null): void;

    /**
     * Get iterator over emitted events
     *
     * Yields all events published to Region::on() notification chain.
     *
     * @return \Iterator<object>
     */
    public function getIterator(): \Iterator;

    /**
     * Generator alternative to getIterator()
     *
     * @return \Generator<object>
     */
    public function events(): \Generator;

    /**
     * Check if execution completed (reached final state)
     */
    public function isComplete(): bool;

    /**
     * Get the wrapped Region
     */
    public function getRegion(): Region;

    /**
     * Get execution configuration
     */
    public function getConfig(): RuntimeConfig;

    /**
     * Spawn a child runtime with optional context sharing
     *
     * Convenience method for creating child runtimes that optionally
     * inherit parent context (ExtendedState Mesh) and/or container.
     *
     * Matches current Meta chain behavior: both parent and child share
     * the same Mesh (parent's mesh extended with child's), making
     * writes bidirectionally visible.
     *
     * @param Region $childRegion The child region to wrap
     * @param bool $shareMesh Share ExtendedState context with child
     * @param bool $shareContainer Share DI container with child
     * @param RuntimeConfig|null $config Additional config (merged with defaults)
     * @return Runtime Child runtime instance
     */
    public function spawn(
        Region $childRegion,
        bool $shareMesh = false,
        bool $shareContainer = false,
        ?RuntimeConfig $config = null,
    ): Runtime;
}
```

**Testing**:
- No tests needed (interface only)

**Dependencies**: None

**Estimated complexity**: Low

---

### 1.4 Create RuntimeConfig Value Object

**Files to create**:
- `src/RuntimeConfig.php`

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Middleware\Mesh;
use Psr\Container\ContainerInterface;

/**
 * Runtime execution configuration
 */
class RuntimeConfig
{
    public function __construct(
        /**
         * Maximum iterations before timeout
         */
        public readonly int $maxIterations = 10000,

        /**
         * Factory for generating trigger payloads
         *
         * fn(int $iteration, Region $region, ?ContainerInterface $container): object
         */
        public readonly ?callable $triggerFactory = null,

        /**
         * Hook called on each iteration
         *
         * fn(Region $region, object $trigger, int $iteration): void
         */
        public readonly ?callable $onIteration = null,

        /**
         * Container for dependency injection
         *
         * Can be shared between piped Runtimes or isolated per Runtime
         */
        public readonly ?ContainerInterface $container = null,

        /**
         * Whether to share container when piping Runtimes
         */
        public readonly bool $shareContainer = false,

        /**
         * Parent Mesh for ExtendedState context inheritance
         *
         * When provided, this Runtime's Mesh will extend the parent Mesh,
         * matching current Meta chain behavior where both parent and child
         * share the same Mesh object (parent's mesh extended with child's).
         *
         * This makes writes bidirectionally visible between parent and child.
         *
         * Only effective when ExtendedState feature is loaded.
         */
        public readonly ?Mesh $parentMesh = null,
    ) {}
}
```

**Testing**:
- Unit test: Verify default values
- Unit test: Verify property assignment

**Dependencies**: None (Mesh and ContainerInterface are external)

**Estimated complexity**: Low

---

### 1.5 Create RuntimeMetaType

**Files to create**:
- `src/RuntimeMetaType.php`

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Noem\State;

/**
 * Metadata type identifier for Runtime references
 */
class RuntimeMetaType
{
    private static ?RuntimeMetaType $instance = null;

    private function __construct() {}

    public static function get(): RuntimeMetaType
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __toString(): string
    {
        return 'runtime';
    }
}
```

**Testing**:
- Unit test: Verify singleton pattern
- Unit test: Verify __toString() returns 'runtime'

**Dependencies**: None

**Estimated complexity**: Low

---

### 1.6 Implement StandardRuntime

**Files to create**:
- `src/StandardRuntime.php`

**Implementation**:
```php
<?php

declare(strict_types=1);

namespace Noem\State;

use Noem\State\Feature\ExtendedState\ExtendedState;
use RuntimeException;

class StandardRuntime implements Runtime
{
    private bool $complete = false;
    private int $iteration = 0;

    public function __construct(
        private readonly Region $region,
        private readonly RuntimeConfig $config = new RuntimeConfig(),
    ) {
        // Initialize context sharing if requested
        if ($this->config->parentMesh) {
            $this->initializeContextSharing();
        }
    }

    public function run(?callable $onComplete = null): void
    {
        $lastResult = null;

        while ($this->iteration < $this->config->maxIterations) {
            $trigger = $this->createTrigger();

            if ($this->config->onIteration) {
                ($this->config->onIteration)(
                    $this->region,
                    $trigger,
                    $this->iteration
                );
            }

            $wasFinal = $this->region->isFinal();
            $this->region->trigger($trigger);
            $nowFinal = $this->region->isFinal();

            $lastResult = $trigger;
            $this->iteration++;

            if ($wasFinal && $nowFinal) {
                break;
            }
        }

        if ($this->iteration >= $this->config->maxIterations && !$this->region->isFinal()) {
            throw new RuntimeException(
                "Runtime reached maximum iterations ({$this->iteration})"
            );
        }

        $this->complete = true;

        if ($onComplete) {
            $onComplete($lastResult);
        }
    }

    public function getIterator(): \Iterator
    {
        // Stream events via generator (not buffered array)
        yield from $this->events();
    }

    public function events(): \Generator
    {
        $emitted = [];

        // Subscribe to notification chain
        $deregister = $this->region->on(function(object $event) use (&$emitted) {
            $emitted[] = $event;
        });

        // Run the region
        $this->run();

        // Cleanup subscription
        $deregister();

        // Yield all emitted events
        foreach ($emitted as $event) {
            yield $event;
        }
    }

    public function isComplete(): bool
    {
        return $this->complete;
    }

    public function getRegion(): Region
    {
        return $this->region;
    }

    public function getConfig(): RuntimeConfig
    {
        return $this->config;
    }

    public function spawn(
        Region $childRegion,
        bool $shareMesh = false,
        bool $shareContainer = false,
        ?RuntimeConfig $config = null,
    ): Runtime {
        $parentMesh = $shareMesh
            ? ExtendedState::getMesh($this->region)
            : null;

        $container = $shareContainer
            ? $this->config->container
            : null;

        // Merge provided config with spawn parameters
        $childConfig = new RuntimeConfig(
            maxIterations: $config?->maxIterations ?? $this->config->maxIterations,
            triggerFactory: $config?->triggerFactory ?? null,
            onIteration: $config?->onIteration ?? null,
            container: $container,
            shareContainer: $shareContainer,
            parentMesh: $parentMesh,
        );

        return new StandardRuntime($childRegion, $childConfig);
    }

    private function createTrigger(): object
    {
        if ($this->config->triggerFactory) {
            return ($this->config->triggerFactory)(
                $this->iteration,
                $this->region,
                $this->config->container
            );
        }

        return new #[\AllowDynamicProperties] class {
            public mixed $result = null;
        };
    }

    /**
     * Initialize context sharing with parent mesh
     *
     * Matches current Meta chain behavior: both parent and child
     * share the same Mesh object (parent's mesh extended with child's).
     */
    private function initializeContextSharing(): void
    {
        $childMesh = ExtendedState::getMesh($this->region);

        if ($childMesh && $this->config->parentMesh) {
            // Match Meta.php:111 - parent extends with child
            $this->config->parentMesh->extendWith($childMesh);
        }
    }
}
```

**Testing**:
- Unit test: run() executes until isFinal()
- Unit test: run() respects maxIterations
- Unit test: run() calls onComplete callback
- Unit test: events() yields emitted events (generator)
- Unit test: spawn() creates child with shared mesh
- Unit test: spawn() creates child with shared container
- Integration test: Child reads parent context via shared mesh
- Integration test: Parent reads child writes via shared mesh (bidirectional)

**Dependencies**:
- Runtime interface (1.3)
- RuntimeConfig (1.4)
- RuntimeMetaType (1.5)
- Region::getChainMail() (1.1)
- ExtendedState::getMesh() (1.2)

**Estimated complexity**: Medium

---

### 1.7 Create RuntimeSpawnFeature

**Files to create**:
- `src/Feature/Runtime/RuntimeSpawnFeature.php`

**Purpose**: Enables `$this->spawnRuntime()` in callbacks (YAML-compatible!)

**Implementation**: See Runtime-Spawn-Feature.md for full design

**Key features**:
- Adds `spawnRuntime()` method to BoundAccess chain
- Retrieves parent Runtime from Meta chain (stored by StandardRuntime)
- Calls `Runtime::spawn()` with builder + options
- Works in YAML (no `use()` needed!)

**Testing**:
- Unit test: `$this->spawnRuntime()` available in callbacks
- Unit test: Throws when not in Runtime context
- Unit test: Throws when builder not provided
- Integration test: Spawning from YAML
- Integration test: Child shares parent context via shareMesh

**Dependencies**:
- StandardRuntime (1.6) - registers runtime reference
- RuntimeMetaType (1.5) - identifies runtime metadata
- ExtendedState (existing) - provides BoundAccess chain

**Estimated complexity**: Medium

**Alternative**: Extend OrthogonalRegions instead of separate feature
- User suggested this option
- Would add to existing OrthogonalRegions feature
- Recommendation: Separate feature for clarity, but can be combined if preferred

---

## Phase 2: Chain-Based Piping

### 2.1 Create RuntimePipeline

**Files to create**:
- `src/Chains/RuntimePipeline.php`

**Implementation**: See Runtime-Chain-Architecture.md for full design

**Key features**:
- Extends `Chain<object, object>`
- `pipe(Runtime, ?callable $filter)` method
- `transform(callable)` method
- `filter(callable)` method
- `run(object $trigger, ?callable $onComplete)` method

**Testing**:
- Unit test: pipe() chains runtimes
- Unit test: filter() drops non-matching events
- Unit test: transform() modifies events
- Integration test: Multi-stage pipeline (parser → processor → sink)

**Dependencies**:
- StandardRuntime (1.5)
- Chain infrastructure (existing)

**Estimated complexity**: Medium

---

### 2.2 Add Pipeline Middleware Helpers

**Files to create**:
- `src/Chains/RuntimePipeline/RateLimitMiddleware.php`
- `src/Chains/RuntimePipeline/BatchingMiddleware.php`
- `src/Chains/RuntimePipeline/RetryMiddleware.php`

**Purpose**: Common middleware patterns for pipelines

**Testing**:
- Unit tests for each middleware
- Integration test: Pipeline with multiple middleware

**Dependencies**: RuntimePipeline (2.1)

**Estimated complexity**: Low (each middleware is simple)

---

### 2.3 Add Tee/Merge Patterns

**Files to create**:
- `src/Chains/RuntimePipeline/TeePipeline.php`
- `src/Chains/RuntimePipeline/MergePipeline.php`

**Purpose**: Fan-out and fan-in patterns

**Testing**:
- Unit test: Tee sends to multiple branches
- Unit test: Merge collects from multiple sources
- Integration test: Complex pipeline with tee and merge

**Dependencies**: RuntimePipeline (2.1)

**Estimated complexity**: Medium

---

## Phase 3: Holon Integration

### 3.1 Update Holon to Support Runtime Return

**Files to modify**:
- `src/Feature/Loader/Holon.php`

**Changes**:
```php
class Holon
{
    /**
     * Bootstrap a complete state machine from YAML
     *
     * @param string $yaml The YAML content or file path
     * @param array $options Additional options:
     *   - 'builderArgs': Additional builder arguments
     *   - 'returnRegion': Return Region instead of Runtime (default: false)
     * @return Runtime|Region Returns Runtime by default, Region if returnRegion=true
     */
    public static function fromYaml(string $yaml, array $options = []): Runtime|Region
    {
        // ... existing bootstrap logic ...

        $region = $builder->build($builderArgs);

        // Support legacy returnRegion option
        if ($options['returnRegion'] ?? false) {
            // Legacy: Return Region directly
            // Handle autoRun if configured
            if ($eventLoopConfig['autoRun'] ?? false) {
                self::runEventLoop($region, $eventLoopConfig, $container);
            }
            return $region;
        }

        // Modern: Return Runtime
        $runtimeConfig = new RuntimeConfig(
            maxIterations: $eventLoopConfig['maxIterations'] ?? 10000,
            triggerFactory: $eventLoopConfig['trigger'] ?? null,
            onIteration: $eventLoopConfig['onIteration'] ?? null,
            container: $container,
            shareContainer: $eventLoopConfig['shareContainer'] ?? false,
        );

        $runtime = new StandardRuntime($region, $runtimeConfig);

        // Handle autoRun
        if ($eventLoopConfig['autoRun'] ?? false) {
            $runtime->run();
        }

        return $runtime;
    }

    // Keep existing runEventLoop() for legacy support
    private static function runEventLoop(...) { /* existing code */ }
}
```

**Elaboration on 'returnRegion' flag**:
- **Default behavior** (no flag): Returns `Runtime` (modern API)
- **Legacy behavior** (`returnRegion: true`): Returns `Region` (backwards compatible)
- This allows gradual migration:
  - Phase 1: Both supported, Runtime is default
  - Phase 2 (1-2 releases later): Deprecate `returnRegion` option
  - Phase 3 (2-3 releases later): Remove `returnRegion`, always return Runtime

**Testing**:
- Unit test: fromYaml() returns Runtime by default
- Unit test: fromYaml(['returnRegion' => true]) returns Region
- Integration test: YAML with eventLoop config works with Runtime
- Integration test: autoRun executes correctly with Runtime

**Dependencies**:
- StandardRuntime (1.5)
- RuntimeConfig (1.4)

**Estimated complexity**: Low (mostly refactoring existing code)

---

### 3.2 Deprecate SelfContainedLoader

**Files to modify**:
- `src/Feature/Loader/SelfContainedLoader.php`

**Changes**:
```php
/**
 * @deprecated Use Holon::fromYaml() instead
 * This class will be removed in version X.Y.Z
 */
class SelfContainedLoader
{
    /**
     * @deprecated Use Holon::fromYaml() instead
     */
    public static function fromYaml(string $yaml, array $options = []): mixed
    {
        trigger_error(
            'SelfContainedLoader is deprecated. Use Holon::fromYaml() instead.',
            E_USER_DEPRECATED
        );

        // Delegate to Holon with returnRegion for backwards compat
        return Holon::fromYaml($yaml, array_merge($options, ['returnRegion' => true]));
    }
}
```

**Testing**:
- Unit test: Verify deprecation notice is triggered
- Integration test: Verify SelfContainedLoader still works (delegates to Holon)

**Documentation**:
- Add migration guide: SelfContainedLoader → Holon
- Update CHANGELOG with deprecation notice

**Dependencies**: Holon (3.1)

**Estimated complexity**: Low

---

### 3.3 Update Holon YAML Schema

**Files to modify**:
- Documentation for YAML eventLoop section

**Add support for**:
```yaml
machine:
  eventLoop:
    autoRun: true
    maxIterations: 5000
    shareContainer: true
    trigger: !php return new MyTrigger($iteration);
    onIteration: !php logProgress($region, $trigger, $iteration);
```

**Testing**:
- Integration test: YAML with all eventLoop options
- Integration test: Runtime respects YAML config

**Dependencies**: Holon (3.1)

**Estimated complexity**: Low (documentation + validation)

---

## Phase 4: Documentation & Examples

### 4.1 Update README

**Files to modify**:
- `README.md`

**Add sections**:
- Runtime overview
- Basic usage example
- Link to Runtime.md proposal
- Migration guide from Region to Runtime

**Dependencies**: All previous phases complete

**Estimated complexity**: Low

---

### 4.2 Create "Composing Machines" Guide

**Files to create**:
- `docs/guides/composing-machines.md`

**Content**:
- Horizontal composition (piping)
- Vertical embedding (sub-runtimes)
- Context sharing strategies
- Real-world examples

**Dependencies**: Phase 2 (piping) complete

**Estimated complexity**: Medium (comprehensive guide)

---

### 4.3 Create "Sync vs Async Execution" Guide

**Files to create**:
- `docs/guides/sync-async-execution.md`

**Content**:
- How Runtime supports both
- Amp examples
- ReactPHP examples
- Event streaming patterns

**Dependencies**: StandardRuntime (1.5)

**Estimated complexity**: Medium

---

### 4.4 Update Feature Documentation

**Files to modify**:
- `src/Feature/ExtendedState/CLAUDE.md`

**Add section**:
- Context sharing with Runtime
- `ExtendedState::getMesh()` helper
- Runtime::spawn() examples

**Dependencies**: ExtendedState::getMesh() (1.2)

**Estimated complexity**: Low

---

## Phase 5: Advanced Features & Optimization

### 5.1 Async-Specific Runtime Variants

**Files to create**:
- `src/AmpRuntime.php` (Amp integration)
- `src/ReactRuntime.php` (ReactPHP integration)

**Purpose**: Optimized runtimes for async frameworks

**Testing**:
- Integration tests with Amp
- Integration tests with ReactPHP

**Dependencies**: StandardRuntime (1.5)

**Estimated complexity**: High (requires async framework expertise)

---

### 5.2 Streaming Optimization

**Files to modify**:
- `src/StandardRuntime.php`

**Add**:
- Configurable event buffering strategies
- Memory-efficient streaming for large event volumes
- Backpressure handling

**Testing**:
- Performance tests with large event streams
- Memory profiling

**Dependencies**: StandardRuntime (1.5)

**Estimated complexity**: Medium

---

### 5.3 Metrics & Observability

**Files to create**:
- `src/RuntimeMetrics.php`
- `src/Chains/RuntimePipeline/MetricsMiddleware.php`

**Features**:
- Event counts
- Execution time tracking
- State transition metrics
- Pipeline throughput

**Testing**:
- Unit tests for metrics collection
- Integration test: Metrics in real pipeline

**Dependencies**: StandardRuntime (1.5), RuntimePipeline (2.1)

**Estimated complexity**: Medium

---

## Testing Strategy

### Unit Tests

**Per component**:
- `tests/PHPUnit/Unit/Runtime/StandardRuntimeTest.php`
- `tests/PHPUnit/Unit/Runtime/RuntimeConfigTest.php`
- `tests/PHPUnit/Unit/Chains/RuntimePipelineTest.php`

**Coverage goals**: 90%+ for all Runtime components

---

### Integration Tests

**Scenarios**:
- `tests/PHPUnit/Integration/Runtime/BasicExecutionTest.php`
- `tests/PHPUnit/Integration/Runtime/PipelineCompositionTest.php`
- `tests/PHPUnit/Integration/Runtime/ContextSharingTest.php`
- `tests/PHPUnit/Integration/Runtime/HolonIntegrationTest.php`

---

### E2E Tests

**Real-world machines**:
- Convert existing E2E tests to use Runtime
- Test webserver machine with Runtime
- Test complex pipelines with multiple stages

---

## Migration Path

### For Existing Code

**Phase 1** (Immediately after implementation):
- All existing code continues working
- Region usage unchanged
- Holon can return Region via `returnRegion: true`

**Phase 2** (1-2 releases later):
- Deprecate `returnRegion` option
- Update examples to use Runtime
- Provide migration tooling

**Phase 3** (2-3 releases later):
- Remove `returnRegion` support
- Remove SelfContainedLoader
- Holon always returns Runtime

---

## Dependencies Graph

```
1.1 Region::getChainMail()
    ↓
1.2 ExtendedState::getMesh()
    ↓
1.3 Runtime interface
1.4 RuntimeConfig
1.5 RuntimeMetaType
    ↓
1.6 StandardRuntime (registers runtime reference)
    ↓
1.7 RuntimeSpawnFeature (enables $this->spawnRuntime())
    ├─→ 2.1 RuntimePipeline
    │       ↓
    │   2.2 Middleware helpers
    │   2.3 Tee/Merge
    └─→ 3.1 Holon integration
            ↓
        3.2 Deprecate SelfContainedLoader
            ↓
        3.3 YAML schema
            ↓
        4.1-4.4 Documentation
            ↓
        5.1-5.3 Advanced features
```

---

## Rollout Timeline (Suggested)

**Week 1-2**: Phase 1 (Core Infrastructure)
- Spec-driven: Write specs first
- Implement core components
- Unit tests

**Week 3**: Phase 2 (Chain-Based Piping)
- Spec-driven: Pipeline specs
- Implement RuntimePipeline
- Integration tests

**Week 4**: Phase 3 (Holon Integration)
- Update Holon
- Deprecate SelfContainedLoader
- Migration testing

**Week 5**: Phase 4 (Documentation)
- Guides and examples
- Update README
- Review and polish

**Week 6+**: Phase 5 (Advanced Features)
- Async variants
- Optimization
- Metrics

---

## Success Criteria

- [ ] All existing tests pass
- [ ] 90%+ code coverage for Runtime components
- [ ] Zero breaking changes for existing Region usage
- [ ] Documentation complete and accurate
- [ ] Real-world machine (webserver) works with Runtime
- [ ] Performance: Runtime overhead < 5% vs direct Region usage
- [ ] Migration path clear and tested

---

## Risk Mitigation

**Risk**: Breaking existing code
- **Mitigation**: Feature flags, gradual rollout, extensive testing

**Risk**: Performance regression
- **Mitigation**: Benchmarking, streaming (not buffering), profiling

**Risk**: Complexity for users
- **Mitigation**: Comprehensive docs, examples, simple defaults

**Risk**: Mesh sharing doesn't match Meta chain behavior
- **Mitigation**: Direct code reuse from Meta.php, integration tests

---

## Notes

- Follow spec-driven development: specs first, then implementation
- Each phase should be independently reviewable
- Testing is critical - don't skip it
- Document as you go, not at the end
- Consider this a living document - update as we learn