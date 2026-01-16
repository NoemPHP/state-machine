# Persistence Layer - Core Proposal

**Status**: Draft
**Created**: 2025-12-28
**Related**: [README.md](./README.md)

## Table of Contents

1. [Overview](#overview)
2. [Problem Statement](#problem-statement)
3. [Architecture](#architecture)
4. [Core Components](#core-components)
5. [Serialization Strategy](#serialization-strategy)
6. [Opt-In/Opt-Out Mechanism](#opt-inopt-out-mechanism)
7. [Implementation Phases](#implementation-phases)
8. [Use Cases](#use-cases)
9. [Extension Points](#extension-points)
10. [Open Questions](#open-questions)

---

## Overview

The **Persistence Layer** provides a generic, feature-based system for **serializing and rehydrating entire Region state machines**, enabling pause/resume, crash recovery, debugging snapshots, and future database-backed Region collections.

**Design Principles**:
1. **Optional Feature**: Zero changes to core Region/Runtime - pure wrapper pattern
2. **Selective Serialization**: Fine-grained control over what gets persisted (whole regions, specific context keys, custom types)
3. **Format Agnostic**: Abstract serialization interface supporting JSON, binary, database, etc.
4. **Robust Error Handling**: Graceful degradation when objects can't be serialized
5. **MetaType Support**: Custom serialization strategies for framework-internal types
6. **Future-Proof**: Extensible for database-backed Region collections, time-travel debugging, distributed state sync

### Key Value Proposition

```php
// Pause execution, serialize to storage
$snapshot = $persistence->capture($runtime);
file_put_contents('machine.snapshot', $snapshot);

// Later: resume from exact same point
$snapshot = file_get_contents('machine.snapshot');
$runtime = $persistence->restore($snapshot);
$runtime->run(); // Continues where it left off
```

---

## Problem Statement

### Current Limitations

1. **No State Persistence**
   - Regions exist only in memory
   - Process termination = complete state loss
   - No way to pause long-running workflows

2. **Difficult Debugging**
   - Can't capture "crash dumps" of Region state
   - No snapshot-based debugging
   - Can't reproduce exact state for testing

3. **No Horizontal Scaling**
   - Can't serialize Region to move between processes
   - No distributed state management
   - Can't offload to job queues

4. **Limited Workflow Support**
   - Can't implement saga patterns (pause, human intervention, resume)
   - No long-lived stateful processes
   - Can't persist awaiting state machines

### Requirements

| Requirement | Priority | Notes |
|-------------|----------|-------|
| Serialize Region internal state | P0 | currentState, dispatched queue, isFinal status |
| Serialize Runtime execution state | P0 | iteration count, completion status, config |
| Serialize ExtendedState context | P0 | All `$this->get()` data |
| Serialize Meta chain data | P1 | Custom MetaTypes (ContextMetaType, etc.) |
| Serialize Message correlation state | P1 | Pending responses, reply handlers |
| Handle closures gracefully | P0 | Skip or use Opis/Closure or warn |
| Selective opt-out | P0 | Exclude sensitive/transient data |
| Schema validation on restore | P1 | Verify snapshot compatibility |
| Version migration | P2 | Handle schema evolution |
| Pluggable backends | P1 | JSON, Database, Redis, etc. |

---

## Architecture

### High-Level Design

```
┌─────────────────────────────────────────────────────────────────┐
│                      PersistenceFeature                         │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────┐         ┌───────────────────┐            │
│  │ PersistenceBackend│◄────────┤ SerializationBackend          │
│  │   (Interface)     │         │  - JsonBackend                 │
│  │                   │         │  - BinaryBackend (future)      │
│  │  - serialize()    │         │  - DatabaseBackend (future)    │
│  │  - deserialize()  │         └───────────────────┘            │
│  └──────────────────┘                                            │
│           ▲                                                      │
│           │                                                      │
│  ┌────────┴──────────────┐                                      │
│  │   PersistenceManager  │                                      │
│  │                       │                                      │
│  │  capture(Runtime)     │──────┐                               │
│  │  restore(data)        │      │                               │
│  └───────────────────────┘      │                               │
│           │                     │                               │
│           │ delegates to        │                               │
│           ▼                     ▼                               │
│  ┌─────────────────┐   ┌─────────────────┐                     │
│  │ RegionSerializer│   │ ContextSerializer│                     │
│  │                 │   │                  │                     │
│  │ - Serializes:   │   │ - ExtendedState  │                     │
│  │   * currentState│   │ - Meta chain     │                     │
│  │   * dispatched  │   │ - Custom MetaTypes                     │
│  │   * isFinal     │   └─────────────────┘                     │
│  │   * Events      │                                            │
│  └─────────────────┘   ┌─────────────────┐                     │
│                        │ RuntimeSerializer│                     │
│                        │                  │                     │
│                        │ - iteration      │                     │
│                        │ - complete       │                     │
│                        │ - config         │                     │
│                        └─────────────────┘                     │
│                                                                  │
│  ┌────────────────────────────────────────┐                     │
│  │   SerializationPolicy (Opt-in/out)     │                     │
│  │                                         │                     │
│  │   - Exclude specific context keys      │                     │
│  │   - Mark fields as @transient          │                     │
│  │   - Custom serializers for types       │                     │
│  └────────────────────────────────────────┘                     │
└─────────────────────────────────────────────────────────────────┘

Flow:
  1. User calls: $snapshot = $persistence->capture($runtime)
  2. PersistenceManager orchestrates serialization:
     a. RuntimeSerializer → captures Runtime state
     b. RegionSerializer → captures Region internal state
     c. ContextSerializer → captures ExtendedState + Meta
  3. Backend formats data (JSON/binary/etc.)
  4. Returns serialized string/blob

Restore Flow:
  1. User calls: $runtime = $persistence->restore($snapshot)
  2. Backend deserializes to array structure
  3. Validators check schema version, structure
  4. RegionBuilder reconstructs Region from serialized state
  5. Runtime wraps restored Region
  6. Returns executable Runtime
```

---

## Core Components

### 1. PersistenceFeature

```php
namespace Noem\State\Feature\Persistence;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;

class PersistenceFeature implements Feature
{
    public function __construct(
        private readonly PersistenceBackend $backend = new JsonBackend(),
        private readonly SerializationPolicy $policy = new SerializationPolicy()
    ) {}

    public function __invoke(ChainMail $chainMail): void
    {
        // Supply PersistenceManager to ChainMail
        $chainMail->supply(fn() => new PersistenceManager(
            $this->backend,
            $this->policy,
            $chainMail
        ));

        // Hook into Meta chain for serialization metadata
        $chainMail->use(function (
            Chains\Meta $meta,
            PersistenceManager $persistence
        ) {
            // Register serialization hooks for custom MetaTypes
            // This allows features to define how their MetaType data serializes
        });
    }
}
```

**Key Points**:
- Pure Feature pattern - no core changes
- Supplies PersistenceManager to container
- Hooks into existing chains for metadata

### 2. PersistenceManager

```php
namespace Noem\State\Feature\Persistence;

use Noem\State\Region;
use Noem\State\Runtime;

class PersistenceManager
{
    public function __construct(
        private readonly PersistenceBackend $backend,
        private readonly SerializationPolicy $policy,
        private readonly ChainMail $chainMail
    ) {}

    /**
     * Capture complete Runtime state as serialized snapshot
     */
    public function capture(Runtime $runtime): string
    {
        $snapshot = [
            'version' => '1.0.0',
            'timestamp' => time(),
            'runtime' => $this->serializeRuntime($runtime),
            'region' => $this->serializeRegion($runtime->getRegion()),
            'context' => $this->serializeContext($runtime->getRegion()),
            'meta' => $this->serializeMeta($runtime->getRegion()),
        ];

        return $this->backend->serialize($snapshot);
    }

    /**
     * Restore Runtime from serialized snapshot
     */
    public function restore(string $data): Runtime
    {
        $snapshot = $this->backend->deserialize($data);

        // Validate snapshot version/schema
        $this->validateSnapshot($snapshot);

        // Rebuild Region from snapshot
        $region = $this->restoreRegion($snapshot['region']);

        // Restore context data
        $this->restoreContext($region, $snapshot['context']);

        // Restore meta chain data
        $this->restoreMeta($region, $snapshot['meta']);

        // Wrap in Runtime with restored config
        $config = $this->restoreRuntimeConfig($snapshot['runtime']['config']);
        $runtime = new StandardRuntime($region, $config);

        // Restore Runtime internal state
        $this->restoreRuntimeState($runtime, $snapshot['runtime']);

        return $runtime;
    }

    /**
     * Create snapshot without stopping execution
     */
    public function snapshot(Runtime $runtime): string
    {
        return $this->capture($runtime);
    }

    // Private serialization methods...
}
```

### 3. PersistenceBackend Interface

```php
namespace Noem\State\Feature\Persistence;

interface PersistenceBackend
{
    /**
     * Serialize snapshot data to storage format
     */
    public function serialize(array $snapshot): string;

    /**
     * Deserialize storage format to snapshot array
     */
    public function deserialize(string $data): array;

    /**
     * Optional: Validate serialized data before deserialize
     */
    public function validate(string $data): bool;
}
```

### 4. JsonBackend (Default Implementation)

```php
namespace Noem\State\Feature\Persistence\Backend;

use Noem\State\Feature\Persistence\PersistenceBackend;
use Noem\State\Feature\Persistence\Exception\SerializationException;

class JsonBackend implements PersistenceBackend
{
    public function __construct(
        private readonly int $flags = JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
    ) {}

    public function serialize(array $snapshot): string
    {
        try {
            return json_encode($snapshot, $this->flags);
        } catch (\JsonException $e) {
            throw new SerializationException(
                "Failed to serialize snapshot: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    public function deserialize(string $data): array
    {
        try {
            return json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new SerializationException(
                "Failed to deserialize snapshot: {$e->getMessage()}",
                previous: $e
            );
        }
    }

    public function validate(string $data): bool
    {
        try {
            json_decode($data, flags: JSON_THROW_ON_ERROR);
            return true;
        } catch (\JsonException) {
            return false;
        }
    }
}
```

### 5. SerializationPolicy

```php
namespace Noem\State\Feature\Persistence;

class SerializationPolicy
{
    private array $excludedKeys = [];
    private array $customSerializers = [];
    private bool $skipClosures = true;

    /**
     * Exclude specific context keys from serialization
     */
    public function exclude(string ...$keys): self
    {
        $this->excludedKeys = [...$this->excludedKeys, ...$keys];
        return $this;
    }

    /**
     * Register custom serializer for a specific type
     *
     * @template T
     * @param class-string<T> $type
     * @param callable(T): array $serializer
     * @param callable(array): T $deserializer
     */
    public function registerSerializer(
        string $type,
        callable $serializer,
        callable $deserializer
    ): self {
        $this->customSerializers[$type] = [
            'serialize' => $serializer,
            'deserialize' => $deserializer
        ];
        return $this;
    }

    /**
     * Check if a context key should be excluded
     */
    public function shouldExclude(string $key): bool
    {
        return in_array($key, $this->excludedKeys, strict: true);
    }

    /**
     * Get custom serializer for type if registered
     */
    public function getSerializer(string $type): ?array
    {
        return $this->customSerializers[$type] ?? null;
    }

    /**
     * Whether to skip closures (default: true)
     */
    public function skipClosures(bool $skip = true): self
    {
        $this->skipClosures = $skip;
        return $this;
    }

    public function shouldSkipClosures(): bool
    {
        return $this->skipClosures;
    }
}
```

---

## Serialization Strategy

### Region Internal State

**What needs to be captured**:

```php
// From Region.php
private string $currentState;        // ✅ Simple string
private array $dispatched = [];      // ⚠️ Array of objects
private bool $initialStateEntered;   // ✅ Simple bool
```

**Serialization approach**:

```php
private function serializeRegion(Region $region): array
{
    // Use reflection to access private properties
    $reflection = new \ReflectionClass($region);

    $currentState = $reflection->getProperty('currentState');
    $currentState->setAccessible(true);

    $dispatched = $reflection->getProperty('dispatched');
    $dispatched->setAccessible(true);

    $initialStateEntered = $reflection->getProperty('initialStateEntered');
    $initialStateEntered->setAccessible(true);

    return [
        'currentState' => $currentState->getValue($region),
        'dispatched' => $this->serializeDispatchedQueue(
            $dispatched->getValue($region)
        ),
        'initialStateEntered' => $initialStateEntered->getValue($region),
        'states' => $this->extractStateDefinitions($region),
        'transitions' => $this->extractTransitions($region),
    ];
}

private function serializeDispatchedQueue(array $dispatched): array
{
    return array_map(
        fn(object $event) => $this->serializeEvent($event),
        $dispatched
    );
}

private function serializeEvent(object $event): array
{
    // Use Message serialization pattern if available
    if ($event instanceof \JsonSerializable) {
        return [
            'type' => get_class($event),
            'data' => $event->jsonSerialize()
        ];
    }

    // Fallback: stdClass or generic object
    return [
        'type' => get_class($event),
        'data' => get_object_vars($event)
    ];
}
```

### Runtime State

```php
// From Runtime.php
private int $iteration = 0;          // ✅ Simple int
private bool $complete = false;      // ✅ Simple bool
private bool $completionFired = false; // ✅ Simple bool
private readonly RuntimeConfig $config; // ⚠️ Object

private function serializeRuntime(Runtime $runtime): array
{
    $reflection = new \ReflectionClass($runtime);

    return [
        'type' => get_class($runtime),
        'iteration' => $this->getProperty($reflection, 'iteration', $runtime),
        'complete' => $this->getProperty($reflection, 'complete', $runtime),
        'completionFired' => $this->getProperty($reflection, 'completionFired', $runtime),
        'config' => $this->serializeRuntimeConfig($runtime->getConfig()),
    ];
}

private function serializeRuntimeConfig(RuntimeConfig $config): array
{
    return [
        'maxIterations' => $config->maxIterations,
        'onIteration' => null, // Closures excluded
        'onComplete' => null,  // Closures excluded
        'triggerFactory' => null, // Closures excluded
    ];
}
```

### ExtendedState Context

```php
private function serializeContext(Region $region): array
{
    $meta = $this->chainMail->get(Chains\Meta::class);
    $contextParams = new Params\Meta($region, ContextMetaType::get());
    $contextData = $meta->call($contextParams);

    $serialized = [];
    foreach ($contextData as $key => $value) {
        // Apply policy - skip excluded keys
        if ($this->policy->shouldExclude($key)) {
            continue;
        }

        // Apply custom serializers if registered
        if (is_object($value)) {
            $type = get_class($value);
            if ($serializer = $this->policy->getSerializer($type)) {
                $serialized[$key] = [
                    '__type' => $type,
                    '__custom' => true,
                    'data' => $serializer['serialize']($value)
                ];
                continue;
            }
        }

        // Handle closures
        if ($value instanceof \Closure) {
            if ($this->policy->shouldSkipClosures()) {
                continue; // Skip
            }
            // Could use Opis/Closure here if needed
            throw new SerializationException(
                "Cannot serialize closure for key '{$key}'. " .
                "Either exclude it via policy or install opis/closure."
            );
        }

        // Standard serialization
        $serialized[$key] = $this->serializeValue($value);
    }

    return $serialized;
}

private function serializeValue(mixed $value): mixed
{
    if ($value instanceof \JsonSerializable) {
        return [
            '__type' => get_class($value),
            'data' => $value->jsonSerialize()
        ];
    }

    if (is_array($value)) {
        return array_map(
            fn($v) => $this->serializeValue($v),
            $value
        );
    }

    if (is_object($value)) {
        return [
            '__type' => get_class($value),
            'data' => get_object_vars($value)
        ];
    }

    // Scalar values
    return $value;
}
```

### Meta Chain Data (Custom MetaTypes)

```php
private function serializeMeta(Region $region): array
{
    // This is extensible - features can register their MetaTypes
    // for serialization via hooks

    $meta = $this->chainMail->get(Chains\Meta::class);
    $serialized = [];

    // Get all registered MetaType serializers
    foreach ($this->metaTypeSerializers as $type => $serializer) {
        $metaParams = new Params\Meta($region, $type);
        $data = $meta->call($metaParams);

        if ($data !== null) {
            $serialized[$type] = $serializer($data);
        }
    }

    return $serialized;
}

// Features register MetaType serializers:
// $persistence->registerMetaTypeSerializer(
//     ContextMetaType::get(),
//     fn(Mesh $mesh) => iterator_to_array($mesh)
// );
```

---

## Opt-In/Opt-Out Mechanism

### 1. Policy-Based Exclusion

```php
// Exclude sensitive/transient data
$policy = (new SerializationPolicy())
    ->exclude('password', 'api_key', 'session_token')
    ->exclude('temp_data', 'cache');

$persistence = new PersistenceFeature(
    backend: new JsonBackend(),
    policy: $policy
);
```

### 2. Attribute-Based Exclusion

```php
namespace Noem\State\Feature\Persistence\Attributes;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Transient
{
    // Mark properties/context keys as non-serializable
}

// Usage in custom Message/Event classes:
class CustomEvent
{
    public function __construct(
        public readonly string $id,

        #[Transient]
        public readonly \Closure $callback,  // Excluded from serialization

        #[Transient]
        public readonly resource $handle     // Excluded from serialization
    ) {}
}
```

### 3. Custom Serializers for Types

```php
// Register custom serialization for DateTime
$policy->registerSerializer(
    \DateTime::class,
    serialize: fn(\DateTime $dt) => $dt->format(\DateTime::ATOM),
    deserialize: fn(string $str) => new \DateTime($str)
);

// Register custom serialization for your domain objects
$policy->registerSerializer(
    OrderId::class,
    serialize: fn(OrderId $id) => $id->toString(),
    deserialize: fn(string $str) => OrderId::fromString($str)
);
```

### 4. Per-Region Opt-Out

```php
// Via YAML config
context:
  persistence:
    enabled: false  # Disable for this region

# Or selective:
context:
  persistence:
    exclude:
      - temp_calculations
      - cache_data
```

---

## Implementation Phases

### Phase 1: Foundation (MVP)

**Goal**: Basic JSON serialization/deserialization of Region + Runtime

- [ ] PersistenceFeature skeleton
- [ ] PersistenceBackend interface
- [ ] JsonBackend implementation
- [ ] PersistenceManager with capture/restore
- [ ] RegionSerializer (currentState, dispatched, isFinal)
- [ ] RuntimeSerializer (iteration, complete, config - minus closures)
- [ ] Basic integration tests

**Success Criteria**:
```php
$runtime = createSimpleRuntime();
$runtime->run(5); // Execute 5 steps

$snapshot = $persistence->capture($runtime);
$restored = $persistence->restore($snapshot);

$restored->run(); // Continues from step 5
```

### Phase 2: Context Serialization

**Goal**: Serialize ExtendedState context data

- [ ] ContextSerializer implementation
- [ ] Integration with Meta chain
- [ ] Support for ContextMetaType
- [ ] SerializationPolicy with exclude mechanism
- [ ] Custom serializer registration
- [ ] Tests with complex context data

**Success Criteria**:
```php
->onEnter('state', function($t) {
    $this->set('counter', 42);
    $this->set('items', [1, 2, 3]);
});

$snapshot = $persistence->capture($runtime);
$restored = $persistence->restore($snapshot);

// Context preserved:
// $this->get('counter') === 42
// $this->get('items') === [1, 2, 3]
```

### Phase 3: Advanced Serialization

**Goal**: Handle Messages, custom MetaTypes, closures

- [ ] Message correlation state serialization
- [ ] Reply handler reconstruction (warn on closures)
- [ ] Custom MetaType serializer hooks
- [ ] Optional Opis/Closure integration
- [ ] Attribute-based exclusion (#[Transient])
- [ ] Validation and error reporting

**Success Criteria**:
```php
// Messages with pending responses preserved
$msg = $region->trigger(new RequestMessage());
$snapshot = $persistence->capture($runtime);
$restored = $persistence->restore($snapshot);

// Correlation still works after restore
$msg->then(fn($response) => /* ... */);
```

### Phase 4: Production Hardening

**Goal**: Schema versioning, validation, error recovery

- [ ] Snapshot schema versioning
- [ ] Schema validation on restore
- [ ] Migration framework for version upgrades
- [ ] Detailed error reporting
- [ ] Performance optimization (lazy deserialization)
- [ ] Memory usage optimization

### Phase 5: Extensions (Future)

**Goal**: Alternative backends, advanced features

- [ ] DatabaseBackend (store snapshots in DB)
- [ ] RedisBackend (distributed state)
- [ ] BinaryBackend (compact format via igbinary/msgpack)
- [ ] Incremental snapshots (delta compression)
- [ ] Time-travel debugging (snapshot history)
- [ ] Distributed sync (CRDTs for conflict resolution)

---

## Use Cases

### 1. Long-Running Workflow Pause/Resume

```php
// eCommerce order processing workflow
$orderFlow = Holon::fromYaml('order-workflow.yml')
    ->enableFeatures(
        new ExtendedState(),
        new PersistenceFeature()
    )
    ->bootstrap();

$runtime = new StandardRuntime($orderFlow);

// Process until payment required
$runtime->run();

// User needs to complete payment externally
// Save state and shutdown
$snapshot = $persistence->capture($runtime);
redis()->set("order:{$orderId}:state", $snapshot);

// Later (hours/days): Payment webhook received
$snapshot = redis()->get("order:{$orderId}:state");
$runtime = $persistence->restore($snapshot);

// Continue processing (fulfillment, shipping, etc.)
$runtime->run();
```

### 2. Crash Recovery

```php
// Periodic snapshots during execution
$runtime->run(steps: 1000);
while (!$runtime->isComplete()) {
    // Snapshot every 1000 iterations
    $snapshot = $persistence->snapshot($runtime);
    file_put_contents('/tmp/recovery.snapshot', $snapshot);

    $runtime->run(steps: 1000);
}

// If crash occurs, resume from last snapshot
if (file_exists('/tmp/recovery.snapshot')) {
    $snapshot = file_get_contents('/tmp/recovery.snapshot');
    $runtime = $persistence->restore($snapshot);
    $runtime->run(); // Resume
}
```

### 3. Debugging Snapshots

```php
// Capture state when bug occurs
try {
    $runtime->run();
} catch (BugException $e) {
    $snapshot = $persistence->capture($runtime);
    file_put_contents("/tmp/crash-{$e->getId()}.dump", $snapshot);
    throw $e;
}

// Later: Reproduce exact state for debugging
$snapshot = file_get_contents("/tmp/crash-123.dump");
$runtime = $persistence->restore($snapshot);

// Step through with debugger or add instrumentation
$runtime->run(steps: 1);
```

### 4. Testing with Snapshots

```php
// Create snapshot at interesting state
$runtime = createComplexWorkflow();
$runtime->run(steps: 50); // Run to specific point
$snapshot = $persistence->capture($runtime);

// Test case 1: What happens if we trigger event X?
$r1 = $persistence->restore($snapshot);
$r1->getRegion()->trigger(new EventX());
$this->assertEquals('expected_state', $r1->getRegion()->getCurrentState());

// Test case 2: What happens if we trigger event Y?
$r2 = $persistence->restore($snapshot);
$r2->getRegion()->trigger(new EventY());
$this->assertEquals('different_state', $r2->getRegion()->getCurrentState());
```

### 5. Distributed Job Queue

```php
// Worker 1: Start processing
$runtime = new StandardRuntime($region);
$runtime->run(steps: 100);

// Need to move to different worker (load balancing, scaling)
$snapshot = $persistence->capture($runtime);
queue()->push('process_region', [
    'snapshot' => $snapshot,
    'steps' => 100
]);

// Worker 2: Continue processing
$job = queue()->pop('process_region');
$runtime = $persistence->restore($job['snapshot']);
$runtime->run(steps: $job['steps']);
```

---

## Extension Points

### 1. Database Backend (Future Phase 5)

```php
namespace Noem\State\Feature\Persistence\Backend;

class DatabaseBackend implements PersistenceBackend
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $table = 'region_snapshots'
    ) {}

    public function serialize(array $snapshot): string
    {
        // Store in database, return snapshot ID
        $id = Uuid::v4();
        $json = json_encode($snapshot);

        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->table} (id, data, created_at) VALUES (?, ?, ?)"
        );
        $stmt->execute([$id, $json, time()]);

        return $id; // Return ID, not full data
    }

    public function deserialize(string $id): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT data FROM {$this->table} WHERE id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return json_decode($row['data'], associative: true);
    }
}

// Usage:
$persistence = new PersistenceFeature(
    backend: new DatabaseBackend($pdo)
);

$snapshotId = $persistence->capture($runtime);
// Returns UUID instead of full JSON

// Later:
$runtime = $persistence->restore($snapshotId);
// Loads from database
```

### 2. Region Collections (Future Enhancement)

```php
// Hypothetical eCommerce feature using persistence
class OrderManagementFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->supply(fn() => new OrderRepository(
            $this->database,
            $this->persistence
        ));
    }
}

class OrderRepository
{
    public function save(Order $order, Runtime $runtime): void
    {
        $snapshot = $this->persistence->capture($runtime);
        $this->db->execute(
            'UPDATE orders SET state_snapshot = ? WHERE id = ?',
            [$snapshot, $order->id]
        );
    }

    public function load(OrderId $id): Runtime
    {
        $row = $this->db->fetchOne(
            'SELECT state_snapshot FROM orders WHERE id = ?',
            [$id]
        );

        return $this->persistence->restore($row['state_snapshot']);
    }

    public function findPendingPayment(): array
    {
        // Load all orders in 'pending_payment' state
        $rows = $this->db->fetchAll(
            "SELECT id, state_snapshot FROM orders
             WHERE state_snapshot->>'$.region.currentState' = 'pending_payment'"
        );

        return array_map(
            fn($row) => $this->persistence->restore($row['state_snapshot']),
            $rows
        );
    }
}
```

### 3. Time-Travel Debugging (Future Enhancement)

```php
class TimeravelBackend implements PersistenceBackend
{
    private array $history = [];

    public function capture(Runtime $runtime): string
    {
        $snapshot = parent::capture($runtime);
        $this->history[] = [
            'timestamp' => microtime(true),
            'snapshot' => $snapshot
        ];
        return $snapshot;
    }

    public function rewindTo(float $timestamp): Runtime
    {
        // Find closest snapshot before timestamp
        $closest = null;
        foreach ($this->history as $entry) {
            if ($entry['timestamp'] <= $timestamp) {
                $closest = $entry['snapshot'];
            } else {
                break;
            }
        }

        return $this->restore($closest);
    }

    public function getHistory(): array
    {
        return $this->history;
    }
}
```

### 4. Incremental Snapshots (Delta Compression)

```php
class IncrementalBackend implements PersistenceBackend
{
    private ?array $baseline = null;

    public function serialize(array $snapshot): string
    {
        if ($this->baseline === null) {
            // First snapshot: full capture
            $this->baseline = $snapshot;
            return json_encode(['type' => 'full', 'data' => $snapshot]);
        }

        // Subsequent: only diff
        $diff = $this->computeDiff($this->baseline, $snapshot);
        return json_encode(['type' => 'delta', 'data' => $diff]);
    }

    private function computeDiff(array $old, array $new): array
    {
        // Compute minimal diff between snapshots
        // Could use Myers diff algorithm, array_diff_assoc, etc.
    }

    public function deserialize(string $data): array
    {
        $decoded = json_decode($data, true);

        if ($decoded['type'] === 'full') {
            $this->baseline = $decoded['data'];
            return $this->baseline;
        }

        // Apply delta to baseline
        return $this->applyDiff($this->baseline, $decoded['data']);
    }
}
```

---

## Open Questions

### 1. Closure Handling Strategy

**Question**: How should we handle closures in RuntimeConfig and event handlers?

**Options**:
- A. Skip entirely (warn user, set to null)
- B. Require Opis/Closure library (optional dependency)
- C. Serialize closure source code only (fragile, reflection-based)
- D. Force users to use named functions (serializable as string)

**Recommendation**: **A for MVP**, with option for B as optional enhancement.

**Rationale**: Most production workflows should use named/registered handlers anyway for testability. Closure serialization is complex and fragile.

---

### 2. State Definition Storage

**Question**: Should we serialize the Region's state definitions (Events object, transitions)?

**Context**: Currently Region is built from RegionBuilder which has all state/transition definitions. To restore Region, we either:
- A. Require original YAML/builder code (snapshot only stores runtime state)
- B. Serialize complete state definitions (Events, transition guards, etc.)

**Options**:
- A. **Runtime state only** - User must provide original machine definition to restore
  ```php
  $snapshot = $persistence->capture($runtime);
  // Later:
  $machineDefinition = Holon::fromYaml('workflow.yml')->build();
  $runtime = $persistence->restore($snapshot, $machineDefinition);
  ```

- B. **Full machine definition** - Snapshot includes all state logic
  ```php
  $snapshot = $persistence->capture($runtime); // Includes machine definition
  $runtime = $persistence->restore($snapshot);  // Self-contained
  ```

**Recommendation**: **A for MVP** (requires machine definition), **B for future** (self-contained snapshots).

**Rationale**:
- Option A: Simpler, smaller snapshots, forces users to version their machine definitions
- Option B: More complex (serializing guards/callbacks), but true "pause anywhere" capability

---

### 3. Schema Versioning Strategy

**Question**: How do we handle snapshot format evolution?

**Options**:
- A. Simple version number in snapshot, reject old versions
- B. Migration framework (upgrade v1 → v2 → v3)
- C. Feature flags in snapshot (serialize which features were active)

**Recommendation**: **A for MVP**, **B for production**.

**Implementation**:
```php
// Snapshot format:
[
    'version' => '1.0.0',
    'features' => ['ExtendedState', 'AsyncFeature', 'PersistenceFeature'],
    'schema_hash' => hash('sha256', json_encode($stateDefinitions)),
    // ...
]

// Restore validation:
if ($snapshot['version'] !== PersistenceManager::CURRENT_VERSION) {
    if ($migrator = $this->getMigrator($snapshot['version'])) {
        $snapshot = $migrator->upgrade($snapshot);
    } else {
        throw new IncompatibleSnapshotException(
            "Cannot restore snapshot v{$snapshot['version']} " .
            "(current: " . self::CURRENT_VERSION . ")"
        );
    }
}
```

---

### 4. Connected Regions (Hierarchical Machines)

**Question**: How do we handle parent-child Region relationships?

**Options**:
- A. Serialize only target Region (ignore parent/children)
- B. Serialize entire tree (parent + all children)
- C. Reference-based (serialize IDs, user must restore tree manually)

**Recommendation**: **B for MVP** (serialize entire tree).

**Rationale**: Region state depends on parent context (RECEIVE_META). Must capture full tree for correct restoration.

**Implementation**:
```php
private function serializeRegion(Region $region): array
{
    return [
        'state' => /* ... */,
        'context' => /* ... */,
        'children' => array_map(
            fn(Region $child) => $this->serializeRegion($child),
            $this->getConnectedChildren($region)
        ),
        'connectionFlags' => $this->getConnectionFlags($region),
    ];
}
```

---

### 5. Async Task State

**Question**: How do we handle AsyncFeature coroutines and pending tasks?

**Context**: AsyncFeature uses CoroutineScheduler with yielded generators and pending resolvers.

**Options**:
- A. Don't support (throw error if AsyncFeature active)
- B. Serialize generator state (VERY complex, likely impossible)
- C. Serialize task queue only (lose in-flight coroutine state)
- D. Require async operations to be idempotent (can replay from last checkpoint)

**Recommendation**: **D for production** - Document that async operations must be resumable.

**Guidance**:
```php
// ❌ Bad: Non-resumable async operation
->onEnter('state', function($t) {
    yield fetch('api.example.com/data')->then(
        fn($data) => $this->set('data', $data)
    );
});

// ✅ Good: Resumable async operation
->onEnter('state', function($t) {
    if (!$this->get('data_loaded')) {
        yield fetch('api.example.com/data')->then(
            function($data) {
                $this->set('data', $data);
                $this->set('data_loaded', true);
            }
        );
    }
});
```

---

### 6. Performance vs Completeness Trade-off

**Question**: Should we optimize for snapshot size or completeness?

**Options**:
- A. Small snapshots (exclude metadata, minimal state)
- B. Complete snapshots (everything, easier restoration)
- C. Configurable (user chooses via policy)

**Recommendation**: **C - Configurable via SerializationPolicy**.

**Example**:
```php
// Minimal (production)
$policy = SerializationPolicy::minimal()
    ->excludeInternalMetadata()
    ->excludeTransientState();

// Complete (debugging)
$policy = SerializationPolicy::complete()
    ->includeAllMetadata()
    ->includeStackTraces();

// Custom
$policy = (new SerializationPolicy())
    ->exclude('temp', 'cache')
    ->includeMetadata('context', 'transitions');
```

---

## Summary

The Persistence Layer provides a **production-ready, feature-based solution** for serializing and restoring Region state machines with:

✅ **Zero core changes** - Pure Feature pattern
✅ **Flexible opt-in/opt-out** - Policy-based exclusion, custom serializers
✅ **Format agnostic** - Pluggable backends (JSON, Database, Redis)
✅ **Robust error handling** - Graceful degradation, validation
✅ **Future-proof** - Extensible for collections, time-travel, distributed sync

**Next Steps**:
1. Review proposal with maintainers
2. Create specification (YAML) following spec-driven methodology
3. Implement Phase 1 (MVP) with tests
4. Iterate through Phases 2-4 based on feedback

---

**Last Updated**: 2025-12-28
**Status**: Draft - Awaiting Review
**Estimated Effort**:
- Phase 1 (MVP): ~40 hours
- Phase 2 (Context): ~30 hours
- Phase 3 (Advanced): ~50 hours
- Phase 4 (Hardening): ~40 hours
- **Total**: ~160 hours / 4 weeks for complete implementation
