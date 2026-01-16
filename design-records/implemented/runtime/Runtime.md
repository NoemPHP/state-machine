# Runtime Proposal

**Status**: Draft (Ready for implementation)
**Date**: 2025-12-14  
**Implementation Plan**: See `../IMPLEMENTATION_PLAN.md`

---

## Table of Contents

1. [Overview](#overview)
2. [Problem Statement](#problem-statement)
3. [Architecture](#architecture)
4. [Key Innovation: $this->summon()](#key-innovation-thissummon)
5. [Usage Examples](#usage-examples)
6. [Implementation Details](#implementation-details)
7. [Migration Guide](#migration-guide)

---

## Overview

**Runtime** is an execution context layer between Region and Holon providing:

1. **Event streaming** - Treat state machines as iterators/generators  
2. **Horizontal composition** - Pipe Runtimes through Chain middleware
3. **Vertical embedding** - Dynamically spawn sub-machines via `$this->summon()`
4. **Sync/Async agnostic** - Callback-based API works for both
5. **YAML-compatible** - No external context required

### Architecture Diagram

```
User Code
    ↓
Holon::fromYaml() → Builds Region → Wraps in Runtime
    ↓
Runtime (Abstract Base Class)
    - run(int $steps = 0): bool → FINAL: Registers in RuntimeRegistry, executes loop
        * $steps = 0: Run to completion (blocking)
        * $steps > 0: Run N steps, return true if more available (non-blocking)
    - executeEventLoop(int $maxSteps): bool → Standard event loop (99% of logic)
    - createDefaultTrigger(int $iteration): object → Optional override point
    - events(): Generator → Stream emitted events (generator)
    - spawn(Region $child, ?RuntimeConfig $config): Runtime → Simple factory
    ↓
RuntimeRegistry (WeakMap)
    - Maps Region → Runtime
    - Enables $this->summon() without ChainMail exposure
    ↓
Region (Core State Machine)
    - trigger(event) → Dispatch action
    - isFinal() → Check completion
    - on(listener) → Subscribe to events
    - connect(child, flags) → Set up parent-child relationship
```

---

## Problem Statement

### Current Pain Points

**1. Hidden Execution Logic**

Holon embeds event loop as static method (`runEventLoop()`, Holon.php:329-375):
- Cannot be reused outside Holon
- Cannot be composed with other strategies
- Tightly couples bootstrap to execution

**2. Limited Composability**

Chaining machines requires manual event wiring:

```php
$parser->on(function($event) use ($processor) {
    if ($event instanceof Chunk) {
        $processor->trigger($event);
    }
});
```

**3. Complex Sub-Region Spawning**

Current Connections require:
- Declarative configuration in builder
- Predicate-based spawning rules  
- Complex lifecycle management

Dynamic spawning (based on runtime data) is awkward.

**4. External Context Breaks YAML**

```php
// ❌ This breaks YAML and isolated configs
$parentRuntime = new StandardRuntime($parentRegion);

$builder->state('process')
    ->onEnter(function($t) use ($parentRuntime) {  // Can't do in YAML!
        $childRuntime = $parentRuntime->spawn(...);
    });
```

### Solution

Runtime provides clean separation:
- **Building** (RegionBuilder + Features)
- **Execution** (Runtime)
- **Core Logic** (Region)

Plus **declarative spawning** via `$this->summon()` - works in YAML!

---

## Architecture

### Runtime Base Class

**Key Design**: Runtime is an abstract base class with all standard behavior implemented. Subclasses only customize trigger generation.

```php
/**
 * Base runtime providing event loop and lifecycle management
 * Template method pattern with final run() guarantees registration
 */
abstract class Runtime implements \IteratorAggregate
{
    public function __construct(
        protected readonly Region $region,
        protected readonly RuntimeConfig $config = new RuntimeConfig(),
    ) {}

    private int $iteration = 0;
    private bool $completionCallbackFired = false;

    /**
     * Execute runtime - FINAL to guarantee registration
     *
     * @param int $steps Number of steps to execute (0 = run to completion)
     * @return bool True if more iterations available, false if complete
     */
    final public function run(int $steps = 0): bool
    {
        RuntimeRegistry::register($this->region, $this);
        try {
            $hasMore = $this->executeEventLoop($steps);

            // Fire completion callback exactly once
            if (!$hasMore && !$this->completionCallbackFired) {
                $this->config->onComplete?->call(null);
                $this->completionCallbackFired = true;
                RuntimeRegistry::unregister($this->region);
            }

            return $hasMore;
        } catch (\Throwable $e) {
            RuntimeRegistry::unregister($this->region);
            throw $e;
        }
    }

    /**
     * Standard event loop - same for all runtimes
     * Override createDefaultTrigger() for custom trigger generation
     *
     * @param int $maxSteps Maximum steps to execute (0 = unlimited)
     * @return bool True if more iterations available, false if complete
     */
    protected function executeEventLoop(int $maxSteps = 0): bool
    {
        $stepsToRun = $maxSteps === 0 ? $this->config->maxIterations : $maxSteps;
        $stepCount = 0;

        while ($stepCount < $stepsToRun) {
            if ($this->isComplete()) {
                return false;
            }

            // Use custom factory or default trigger
            $trigger = $this->config->triggerFactory
                ? ($this->config->triggerFactory)($this->iteration, $this->region)
                : $this->createDefaultTrigger($this->iteration);

            $this->config->onIteration?->call(null, $this->region, $trigger, $this->iteration);

            $wasFinal = $this->region->isFinal();
            $this->region->trigger($trigger);
            $nowFinal = $this->region->isFinal();

            $this->iteration++;
            $stepCount++;

            if ($wasFinal && $nowFinal) {
                return false; // Completed
            }
        }

        // Check max iterations
        if ($this->iteration >= $this->config->maxIterations && !$this->region->isFinal()) {
            throw new \RuntimeException(
                "Event loop reached maximum iterations ({$this->iteration})"
            );
        }

        return !$this->isComplete();
    }

    /**
     * Override for custom trigger generation
     * Default: anonymous object with $result property
     */
    protected function createDefaultTrigger(int $iteration): object
    {
        return new #[\AllowDynamicProperties] class {
            public mixed $result = null;
        };
    }

    /** Stream emitted events (generator - no buffering!) */
    public function events(): \Generator
    {
        $emitted = [];
        $deregister = $this->region->on(fn($e) => $emitted[] = $e);
        $this->run();
        $deregister();

        foreach ($emitted as $event) {
            yield $event;
        }
    }

    /** IteratorAggregate implementation */
    public function getIterator(): \Traversable
    {
        return $this->events();
    }

    /** Check completion status */
    public function isComplete(): bool
    {
        return $this->region->isFinal();
    }

    /** Access wrapped region */
    public function getRegion(): Region
    {
        return $this->region;
    }

    /** Access configuration */
    public function getConfig(): RuntimeConfig
    {
        return $this->config;
    }

    /**
     * Spawn child runtime
     * Simple factory - complex setup happens in OrthogonalRegions.summon()
     */
    public function spawn(Region $childRegion, ?RuntimeConfig $config = null): Runtime
    {
        return new static($childRegion, $config ?? new RuntimeConfig());
    }
}
```

### RuntimeConfig

```php
class RuntimeConfig
{
    public function __construct(
        public readonly int $maxIterations = 10000,
        public readonly ?callable $triggerFactory = null,
        public readonly ?callable $onIteration = null,
        public readonly ?callable $onComplete = null,
    ) {}
}
```

**Callbacks**:
- `triggerFactory(int $iteration, Region $region): object` - Custom trigger generation per iteration
- `onIteration(Region $region, object $trigger, int $iteration): void` - Called before each iteration
- `onComplete(): void` - Called exactly once when runtime completes (fired regardless of how many times `run()` was called)

### RuntimeRegistry

**Enables `$this->summon()` without exposing ChainMail**

```php
/**
 * Maps Region instances to their Runtime wrappers
 * Uses WeakMap for automatic cleanup when regions are garbage collected
 */
final class RuntimeRegistry
{
    private static ?WeakMap $runtimes = null;

    private static function init(): void
    {
        if (self::$runtimes === null) {
            self::$runtimes = new \WeakMap();
        }
    }

    public static function register(Region $region, Runtime $runtime): void
    {
        self::init();
        self::$runtimes[$region] = $runtime;
    }

    public static function get(Region $region): ?Runtime
    {
        self::init();
        return self::$runtimes[$region] ?? null;
    }

    public static function unregister(Region $region): void
    {
        self::init();
        unset(self::$runtimes[$region]);
    }
}
```

**Why This Works**:
- ✅ No ChainMail exposure needed
- ✅ No Region modifications
- ✅ Automatic registration in `run()` (template method)
- ✅ Automatic cleanup via WeakMap + finally block
- ✅ No fragile conventions - enforced by framework

### StandardRuntime Implementation

**Minimal - inherits everything from base class**

```php
/**
 * Standard runtime with default trigger generation
 * Uses anonymous object with $result property
 */
final class StandardRuntime extends Runtime
{
    // That's it! Base class provides all functionality
}
```

**For custom trigger generation**:

```php
final class CustomRuntime extends Runtime
{
    protected function createDefaultTrigger(int $iteration): object
    {
        return new MyCustomTrigger($iteration);
    }
}
```

---

## Key Innovation: $this->summon()

### The Problem with External Context

```php
// ❌ Requires use() - breaks YAML!
$parentRuntime = new StandardRuntime($parentRegion);

$builder->state('process')
    ->onEnter(function($t) use ($parentRuntime) {
        $childRuntime = $parentRuntime->spawn(...);
    });
```

### The Solution: Extend OrthogonalRegions

```php
// ✅ Works in YAML - no external context!
$builder = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new OrthogonalRegions(),  // Provides $this->summon()
    )
    ->state('process')
        ->onEnter(function($t) {
            $childRuntime = $this->summon(
                builder: new ProcessorBuilder(),
                shareMesh: true,
            );
            $childRuntime->run();
        })
    ->build();

$runtime = new StandardRuntime($builder);
$runtime->run();
```

### How It Works

1. **Runtime.run()** (final method) registers itself in RuntimeRegistry
2. **OrthogonalRegions** adds `summon()` to BoundAccess chain
3. `summon()` retrieves Runtime from registry, sets up connections, calls `spawn()`
4. ✅ No external context needed!
5. ✅ No ChainMail exposure!
6. ✅ Automatic registration guaranteed by template method pattern

### OrthogonalRegions Extension

**Separation of Concerns**:
- **Runtime.spawn()** - Simple factory (just creates new Runtime)
- **OrthogonalRegions.summon()** - Complex setup (connections, context sharing)

```php
class OrthogonalRegions implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // ... existing static regions code ...

        // NEW: Add summon() method
        $chainMail->use(function (
            BoundAccess $boundAccess,
            Chains\ConnectedRegions $connectedRegions,
        ) {
            $boundAccess->link(function (BoundAccessParams $params, callable $next) use ($connectedRegions) {
                if ($params->type === BoundAccessParams::TYPE_METHOD && $params->name === 'summon') {
                    $args = $params->payload;
                    $builder = $args['builder'] ?? $args[0] ?? null;
                    $shareMesh = $args['shareMesh'] ?? $args[1] ?? false;
                    $config = $args['config'] ?? $args[2] ?? null;

                    if (!$builder instanceof RegionBuilder) {
                        throw new \RuntimeException('summon() requires RegionBuilder');
                    }

                    // Retrieve parent Runtime from registry
                    $parentRuntime = RuntimeRegistry::get($params->region);

                    if (!$parentRuntime) {
                        throw new \RuntimeException('summon() requires Runtime context');
                    }

                    // Build child region
                    $childRegion = $builder->build();

                    // Set up connection for context sharing (Feature has ChainMail access)
                    if ($shareMesh) {
                        $params->region->connect($childRegion, Connection::RECEIVE_META);
                    }

                    // Spawn child runtime (simple factory)
                    return $parentRuntime->spawn($childRegion, $config);
                }
                return $next($params);
            });
        });
    }
}
```

**Key Points**:
- `RuntimeRegistry::get()` retrieves parent runtime (registered in `run()`)
- `$params->region->connect()` sets up context sharing (Feature has access)
- `$parentRuntime->spawn()` just creates new Runtime instance
- Complex logic stays in Feature, Runtime stays simple

### Why "summon"?

**Evocative**: Conjures imagery of calling forth a sub-process  
**Clear intent**: Implies creation and invocation  
**Poetic**: More memorable than `spawnRuntime()` or `createChild()`  
**Concise**: 6 letters, easy to type

---

## Usage Examples

### Basic Execution (Blocking)

```php
$runtime = new StandardRuntime($region, new RuntimeConfig(
    maxIterations: 1000,
    onComplete: fn() => echo "Machine finished!\n",
));

// Run to completion (blocking)
$runtime->run();
```

### Non-Blocking Execution (Cooperative Multitasking)

```php
$runtime = new StandardRuntime($region, new RuntimeConfig(
    onComplete: fn() => echo "All done!\n",
));

// Execute one iteration at a time
while ($runtime->run(steps: 1)) {
    yield; // Give control to other coroutines/tasks
}
```

### Multiple Runtimes (Round-Robin)

```php
$parser = new StandardRuntime($parserRegion);
$validator = new StandardRuntime($validatorRegion);
$processor = new StandardRuntime($processorRegion);

$runtimes = [$parser, $validator, $processor];

// Execute all runtimes cooperatively
while (true) {
    $anyActive = false;
    foreach ($runtimes as $runtime) {
        if ($runtime->run(steps: 1)) {
            $anyActive = true;
        }
    }
    if (!$anyActive) break;

    // Optional: yield control to event loop
    yield;
}
```

### Event Loop Integration (Amp)

```php
use Amp\Loop;

$runtime = new StandardRuntime($region, new RuntimeConfig(
    onComplete: fn() => Loop::stop(),
));

// Run one step every 10ms
Loop::repeat(10, function($watcherId) use ($runtime) {
    if (!$runtime->run(steps: 1)) {
        Loop::cancel($watcherId);
    }
});

Loop::run();
```

### Event Loop Integration (ReactPHP)

```php
use React\EventLoop\Loop;

$runtime = new StandardRuntime($region, new RuntimeConfig(
    onComplete: fn() => Loop::stop(),
));

$timer = Loop::addPeriodicTimer(0.01, function($timer) use ($runtime) {
    if (!$runtime->run(steps: 1)) {
        Loop::cancelTimer($timer);
    }
});

Loop::run();
```

### Batch Processing

```php
// Execute 10 steps at a time, then check conditions
while ($runtime->run(steps: 10)) {
    // Check external conditions
    if ($shouldPause) {
        break;
    }

    // Do other work between batches
    processOtherTasks();
}
```

### Event Streaming

```php
foreach ($runtime->events() as $event) {
    match(true) {
        $event instanceof ChunkParsed => handleChunk($event),
        $event instanceof ErrorOccurred => logError($event),
        default => null,
    };
}
```

### Dynamic Sub-Runtime Spawning

```php
->state('process_document')
    ->onEnter(function($trigger) {
        $childRuntime = $this->summon(
            builder: match($trigger->documentType) {
                'json' => new JsonParserBuilder(),
                'xml' => new XmlParserBuilder(),
            },
            shareMesh: true,
        );

        $childRuntime->run();
    })
```

### YAML Support

```yaml
machine:
  features:
    - class: Noem\State\Feature\ExtendedState\ExtendedState
    - class: Noem\State\Feature\OrthogonalRegions\OrthogonalRegions

states:
  - name: process
    onEnter: !php |
      $childRuntime = $this->summon(
        builder: new App\ProcessorBuilder(),
        shareMesh: true,
      );
      $childRuntime->run();
```

### Pipeline Composition

```php
use Noem\State\Chains\RuntimePipeline;

(new RuntimePipeline())
    ->pipe($parserRuntime)
    ->filter(fn($e) => $e instanceof Chunk)
    ->transform(fn($c) => enrichChunk($c))
    ->pipe($processorRuntime)
    ->run();
```

### Context Sharing (Bidirectional)

```php
// Parent
->state('parent')
    ->onEnter(function($t) {
        $this->set('sharedConfig', ['timeout' => 5000]);

        $childRuntime = $this->summon(
            builder: new ChildBuilder(),
            shareMesh: true,  // Bidirectional sharing!
        );

        $childRuntime->run();

        // Parent can read child's writes
        $status = $this->get('childStatus');  // 'complete'
    })

// Child (built by ChildBuilder)
->state('child')
    ->onEnter(function($t) {
        // Child reads parent's context
        $config = $this->get('sharedConfig');  // ['timeout' => 5000]

        // Child writes to shared context
        $this->set('childStatus', 'complete');  // Parent can read this!
    })
```

---

## Implementation Details

### Context Sharing is Bidirectional

**Current Meta.php behavior** (lines 106-114):

```php
$parent = $first($parentMetaParams);  // Get parent's mesh
$child = $next($metaParams);          // Get child's mesh
$parent->extendWith($child);          // Parent extends with child
return $metaData[$parentRegion]...;   // Both get parent's mesh!
```

**Result**: Both parent and child share the **same Mesh object**.

**Reads**: Check child first, then parent  
**Writes**: Go to child  
**Effect**: Bidirectional visibility - both see each other's changes

**Runtime matches this exactly** when `shareMesh: true` is passed to `summon()`.

### Feature Loading Order

**Critical**: ExtendedState MUST come before OrthogonalRegions!

```php
$builder->enableFeatures(
    new ExtendedState(),     // MUST be first (provides $this-> access)
    new OrthogonalRegions(), // Adds summon() method
);
```

**Why**: OrthogonalRegions uses BoundAccess chain, provided by ExtendedState.

---

## Migration Guide

### From Manual Region Usage

**Before**:
```php
$region = $builder->build();
$region->trigger($event);
```

**After** (opt-in):
```php
$runtime = new StandardRuntime($region);
$runtime->run();

// Or iterate events
foreach ($runtime->events() as $event) {
    handleEvent($event);
}
```

### From Holon

**Before**:
```php
$region = Holon::fromYaml('machine.yaml');
```

**After**:
```php
// Modern (default)
$runtime = Holon::fromYaml('machine.yaml');

// Legacy (backwards compat)
$region = Holon::fromYaml('machine.yaml', ['returnRegion' => true]);
```

**Migration timeline**:
- **Phase 1**: Both supported, Runtime default
- **Phase 2** (1-2 releases): Deprecate `returnRegion` flag
- **Phase 3** (2-3 releases): Remove flag, always return Runtime

### From SelfContainedLoader

**Before**:
```php
$result = SelfContainedLoader::fromYaml('machine.yaml');
```

**After**:
```php
$runtime = Holon::fromYaml('machine.yaml');
```

**Note**: SelfContainedLoader immediately deprecated, delegates to Holon.

---

## Error Handling

### summon() Without Runtime Context

```php
// Region NOT wrapped in Runtime
$region = $builder->build();
$region->trigger($event);  // Calls onEnter with $this->summon()

// ❌ RuntimeException: "summon() requires Runtime context"
```

**Fix**: Wrap in Runtime:
```php
$runtime = new StandardRuntime($region);
$runtime->run();  // ✅ Now summon() works
```

### Missing Builder Parameter

```php
$childRuntime = $this->summon();  // ❌ No builder

// RuntimeException: "summon() requires RegionBuilder"
```

**Fix**: Provide builder:
```php
$childRuntime = $this->summon(builder: new MyBuilder());
```

### ExtendedState Not Loaded

```php
$builder->enableFeatures(
    new OrthogonalRegions(),  // ❌ No ExtendedState!
);

// Fatal: BoundAccess chain not available
```

**Fix**: Load ExtendedState first:
```php
->enableFeatures(
    new ExtendedState(),     // ✅ First
    new OrthogonalRegions(),
)
```

---

## Design Decisions

### 1. Abstract Base Class with Template Method

**Decision**: Runtime is abstract class with final `run()` method

**Rationale**:
- Guarantees registration in RuntimeRegistry (no fragile conventions)
- 99% of logic is identical across all runtimes
- Only customization point: trigger generation
- Template method pattern prevents mistakes

**Benefits**:
- StandardRuntime is literally empty (inherits everything)
- No constructor side effects
- Registration happens at right time (entry point)
- Automatic cleanup in finally block

### 2. RuntimeRegistry with WeakMap

**Decision**: Use static WeakMap to map Region → Runtime

**Rationale**:
- Enables `$this->summon()` without exposing ChainMail from Region
- WeakMap provides automatic garbage collection
- Registration in `run()` is guaranteed by template method
- No Region API changes needed

**Tradeoffs**:
- Global state (but scoped to Runtime concern)
- One Runtime per Region (seems reasonable)

### 3. Separation: Runtime (simple) vs OrthogonalRegions (complex)

**Decision**: Runtime.spawn() is simple factory, OrthogonalRegions.summon() handles complexity

**Rationale**:
- Runtime has no ChainMail access (shouldn't)
- Features (like OrthogonalRegions) DO have ChainMail access
- Complex setup (connections, context sharing) belongs in Feature layer
- Keeps Runtime clean and simple

### 4. Async-Aware Execution: run(int $steps = 0)

**Decision**: Single `run()` method with optional `$steps` parameter

**API**:
```php
// Blocking - run to completion
$runtime->run();

// Non-blocking - run N steps
while ($runtime->run(steps: 1)) {
    yield; // Cooperative multitasking
}
```

**Rationale**:
- **Simple API**: One method, natural default behavior (0 = run to completion)
- **Flexible**: Supports both blocking and non-blocking execution
- **Event loop agnostic**: Works with Amp, ReactPHP, Revolt, custom loops
- **No ambiguity**: `onComplete` in RuntimeConfig (not parameter) - fires exactly once
- **Clean separation**: RuntimeConfig handles lifecycle, `run()` handles execution control

**Benefits**:
- ✅ Cooperative multitasking for webserver-like scenarios
- ✅ Batch processing (run 10 steps, check conditions, repeat)
- ✅ Multiple runtimes in round-robin fashion
- ✅ Integration with any event loop library
- ✅ No need for separate `step()` method - parameter does it all

### 5. Event Streaming: Generator-based

**Decision**: Use generators, not buffered arrays

**Rationale**: Memory-efficient for large event streams

**Implementation**:
```php
public function events(): \Generator {
    foreach ($emitted as $event) {
        yield $event;  // Stream, don't buffer!
    }
}
```

### 6. Context Sharing: Bidirectional

**Decision**: Match existing Meta.php behavior exactly

**Rationale**: Consistency with Connection::RECEIVE_META

**Result**: Both parent and child can read/write shared Mesh

### 7. Spawning: Declarative via $this->summon()

**Decision**: Extend OrthogonalRegions, not separate feature

**Rationale**:
- Conceptually related (region composition)
- YAML-compatible (no external context)
- Consistent with `$this->get()`, `$this->dispatch()` pattern

### 8. Holon Return: Flag-based Migration

**Decision**: Support both Region and Runtime via `returnRegion` flag

**Rationale**: Gradual migration without breaking changes

**Timeline**: Both → Deprecate flag → Remove flag (over 2-3 releases)

### 9. SelfContainedLoader: Immediate Deprecation

**Decision**: Deprecate immediately, delegate to Holon

**Rationale**: Duplicates Holon functionality, serves no clear purpose

---

## Success Criteria

- [ ] All existing tests pass
- [ ] 90%+ code coverage for Runtime components
- [ ] Zero breaking changes for existing Region usage
- [ ] YAML spawning works via `$this->summon()`
- [ ] Documentation complete and accurate
- [ ] Real-world machine (webserver) works with Runtime
- [ ] Performance: Runtime overhead < 5% vs direct Region
- [ ] Migration path clear and tested

---

## Implementation Plan

See `../IMPLEMENTATION_PLAN.md` for detailed roadmap:

**22 tasks across 5 phases**:
- **Phase 1**: Core Infrastructure (7 tasks)
- **Phase 2**: Chain-Based Piping (3 tasks)
- **Phase 3**: Holon Integration (3 tasks)
- **Phase 4**: Documentation (4 tasks)
- **Phase 5**: Advanced Features (3 tasks)

**Timeline**: 6+ weeks (spec-driven development)

---

## Conclusion

Runtime provides:

✅ **Clean separation**: Building vs Execution vs Logic
✅ **Composability**: Pipelines, sub-runtimes, event streaming
✅ **YAML support**: `$this->summon()` works everywhere
✅ **Async-aware**: Single API for blocking and non-blocking execution
✅ **Event loop agnostic**: Works with Amp, ReactPHP, Revolt, custom loops
✅ **Simple implementation**: StandardRuntime is empty, base class does everything
✅ **No API pollution**: No ChainMail exposure, no Region changes
✅ **Guaranteed correctness**: Template method enforces registration
✅ **Backwards compatibility**: 100% - Runtime is opt-in
✅ **Production-ready path**: Spec-driven workflow

**Key Architectural Wins**:
- Abstract base class with 99% of logic implemented
- `run(int $steps = 0)` - elegant API for both sync and async
- RuntimeRegistry enables `$this->summon()` without exposing internals
- Features handle complexity, Runtime stays simple
- Template method pattern prevents mistakes
- WeakMap ensures automatic cleanup
- Cooperative multitasking support (webserver, concurrent tasks)

**Ready for implementation!**
