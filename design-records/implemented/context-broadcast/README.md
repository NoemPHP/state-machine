# Context Broadcast Feature

**Status**: Implemented
**Created**: 2026-01-16

## Overview

This proposal describes a mechanism for **ExtendedStateFeature** to emit events on every context change, enabling external subscribers to consume state changes in a manner similar to a Redux store. This creates a dedicated notification channel for observing a region's state mutations.

## Problem Statement

Currently, ExtendedStateFeature provides `$this->get()` and `$this->set()` context helpers within callbacks, but:

1. **No automatic notifications**: Setting a value does NOT emit any event
2. **No external observability**: There's no public API to observe context changes from outside callbacks
3. **No change history**: Context mutations are invisible to external systems
4. **No nested region awareness**: When nested regions change context, parents have no visibility

External systems (CLI tools, web dashboards, debugging tools, process-wrapper broadcasts) need to observe context changes without modifying the state machine's internal logic.

## Research Findings

### Current Architecture

#### ExtendedState Storage Flow

```
$this->set('key', 'value')
    ↓
Bound::__call('set', ['key', 'value'])
    ↓
BoundAccess Chain (routes to handlers)
    ↓
Set Chain middleware (ExtendedState.php:32-38)
    ↓
Meta Chain → Returns Mesh for region
    ↓
$mesh['key'] = 'value' (ArrayAccess into Mesh)
```

**Key insight**: The **Set Chain** is the interception point for all context writes.

#### Notification System

The existing notification system uses:
- `Notification` chain with global listener array
- Type-based filtering via `SubscriptionFeature`
- `$region->on(callback)` for subscriptions
- `$region->trigger(event)` for dispatch

#### Path Chain (Region Hierarchy)

`Region::path()` returns hierarchical paths like `"root/parent/child"`:

```php
public function path(): string
{
    return $this->path->call($this);  // Delegates to Path chain
}
```

The Path chain:
1. Gets current state name as base
2. Walks up parent chain via `ConnectedRegions`
3. Prepends each parent's state with `/` separator

**Key insight**: The Path chain is currently **minimal** - it only has one middleware that builds the path string. It's effectively unused for extensibility.

### Nested Regions

Two nesting patterns exist:

| Pattern | Setup | Connection | Example |
|---------|-------|------------|---------|
| **Build-time** | `OrthogonalRegions` feature, `regions:` in YAML | Automatic via `ProcessArray`, predicate-based | Parallel state execution |
| **Run-time** | `$this->summon(path)` helper | Manual, parent controls execution | Dynamic machine loading |

Both create parent-child relationships tracked by `ConnectedRegions` chain.

### process-wrapper Machine Patterns

The process-wrapper machine demonstrates broadcasting patterns:
- Multiple `BroadcastChannel` sockets for different event types
- `ControlChannel` for bidirectional communication
- Subscription to inner holon events via `$innerHolon->on()`
- JSON serialization for IPC

## Proposed Solution

### Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│ State Machine Callback                                              │
│ $this->set('counter', 42)                                          │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Set Chain (middleware intercepts)                                   │
│                                                                     │
│   1. Store value in Mesh (existing behavior)                        │
│   2. Emit ContextChange event via Notification chain (NEW)         │
│                                                                     │
│   ContextChange {                                                   │
│     path: "parent/child",    // Full region hierarchy               │
│     key: "counter",          // Changed key                         │
│     value: 42,               // New value                           │
│     previousValue: null,     // Old value (if tracked)              │
│     timestamp: 1705420800,   // When change occurred                │
│   }                                                                 │
└───────────────────────────────┬─────────────────────────────────────┘
                                │
                                ▼
┌─────────────────────────────────────────────────────────────────────┐
│ Notification Chain                                                  │
│                                                                     │
│   ┌─► External subscriber 1 (dashboard)                             │
│   ├─► External subscriber 2 (logger)                                │
│   └─► External subscriber 3 (process-wrapper broadcast)             │
└─────────────────────────────────────────────────────────────────────┘
```

### Component 1: ContextChange Event

```php
namespace Noem\State\Feature\ExtendedState;

readonly class ContextChange implements \JsonSerializable
{
    public function __construct(
        public string $path,           // Region hierarchy path
        public string $key,            // Context key that changed
        public mixed $value,           // New value
        public mixed $previousValue,   // Previous value (null if first set)
        public float $timestamp,       // microtime(true)
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'type' => self::class,
            'path' => $this->path,
            'key' => $this->key,
            'value' => $this->value,
            'previousValue' => $this->previousValue,
            'timestamp' => $this->timestamp,
        ];
    }
}
```

### Component 2: Set Chain Middleware (Broadcasting)

Add middleware to the Set chain that emits `ContextChange` events. Previous value tracking is always enabled.

```php
// In ContextBroadcastFeature

$setChain->link(function (Params\Set $set, callable $next) use ($meta, $notificationChain) {
    // Always get previous value BEFORE setting
    $metaParams = new Params\Meta($set->region, ContextMetaType::get());
    $data = $meta->call($metaParams);
    $previousValue = $data[$set->key] ?? null;

    // Perform the actual set (call next middleware)
    $result = $next($set);

    // Emit change event AFTER successful set
    $changeEvent = new ContextChange(
        path: $set->region->path(),
        key: $set->key,
        value: $set->value,
        previousValue: $previousValue,
        timestamp: microtime(true),
    );

    $notifyParams = new Params\Notify($set->region, $changeEvent);
    $listeners = $notificationChain->call($notifyParams);
    foreach ($listeners as $listener) {
        $listener($changeEvent, $set->region);
    }

    return $result;
}, prepend: false);  // Run AFTER the storage middleware
```

### Component 3: Path Chain Enhancement for Nested Regions (Auto-Connect with Auto-Disconnect)

The current Path chain builds paths correctly for static hierarchies. For dynamic (summon) hierarchies, we need to establish connections automatically.

**Problem**: When using `summon()`, the loaded region is **NOT automatically connected** to the parent region.

**Solution**: Auto-connect on summon with auto-disconnect on coroutine completion.

```php
// In RegionLoader summon registration
private function registerSummonHelper(...): void
{
    $boundAccess->link(function (BoundAccessParams $params, callable $next) {
        if ($params->name !== 'summon') {
            return $next($params);
        }

        $childRegion = Holon::fromYaml($resolvedPath, ['autoRun' => false]);

        // Auto-connect for path tracking
        $disconnect = $this->connectedRegions->connect(
            $params->region,  // parent
            $childRegion,
            Connection::DYNAMIC,
            fn() => true
        );

        // Track for auto-disconnect when coroutine completes
        $this->trackSummonedRegion($params->region, $childRegion, $disconnect);

        return $childRegion;
    });
}
```

**Auto-Disconnect Triggers**:
1. Owning coroutine completes (success or error)
2. Region is explicitly unset from context
3. Parent region is destroyed

### Component 4: Subscription API

External systems subscribe to context changes:

```php
// Subscribe to ALL context changes across ALL regions
$deregister = $region->on(function (ContextChange $change) {
    echo "Context changed: {$change->path}.{$change->key} = " . json_encode($change->value);
});

// Subscribe only to specific region path
$deregister = $region->on(function (ContextChange $change) {
    if (!str_starts_with($change->path, 'workflow/processing')) {
        return;  // Ignore changes outside our scope
    }
    $this->updateDashboard($change);
});
```

### Component 5: Batch Updates (Optional Enhancement)

For performance, support batched context changes:

```php
// In callback
$this->batch(function() {
    $this->set('counter', 1);
    $this->set('status', 'processing');
    $this->set('timestamp', time());
});
// Emits single ContextChangeBatch event with all 3 changes
```

## Nested Region Handling

### Build-Time vs Run-Time Connections

| Scenario | Connection | Path Works | Event Bubbling |
|----------|------------|------------|----------------|
| OrthogonalRegions | Automatic with `RECEIVE_EVENTS` | Yes | Automatic |
| Summoned | Auto-connect with `DYNAMIC` only | Yes | Manual (opt-in) |

### Event Bubbling Strategy (Approved)

**Build-Time (OrthogonalRegions)**: Automatic bubbling via `RECEIVE_EVENTS` flag.

```php
// In Notification chain middleware
$notificationChain->link(function (Params\Notify $notify, callable $next) use ($connectedRegions) {
    $localListeners = $next($notify);

    // Bubble ContextChange to parents with RECEIVE_EVENTS
    if ($notify->event instanceof ContextChange) {
        $parentRegions = $connectedRegions->call(
            new Params\Connection($notify->region, false, Connection::RECEIVE_EVENTS)
        );

        foreach ($parentRegions as $parent) {
            $parent->notificationChain->call(
                new Params\Notify($parent, $notify->event)
            );
        }
    }

    return $localListeners;
});
```

**Run-Time (Summon)**: Manual forwarding by implementation.

```php
->onEnter('processing', function(object $t): \Generator {
    $child = $this->summon('child.yml');
    yield;

    // Opt-in: forward child context changes to parent
    $child->on(fn(ContextChange $e) =>
        $this->region->notificationChain->call(new Notify($this->region, $e))
    );

    // ... use child ...
})
```

This keeps run-time nesting behavior explicit while build-time nesting "just works".

## Implementation Phases

### Phase 1: Basic Context Broadcasting

1. Create `ContextChange` event class
2. Add Set chain middleware to emit events
3. Add tests for single-region context broadcasting
4. Update ExtendedStateFeature or create ContextBroadcastFeature

**Deliverables**:
- `src/Feature/ExtendedState/ContextChange.php`
- Modified `src/Feature/ExtendedState/ExtendedState.php` OR new `src/Feature/ContextBroadcast/ContextBroadcastFeature.php`
- Unit tests for context change emission

### Phase 2: Path Chain Enhancement

1. Implement Option A (auto-connect on summon) OR Option C (context-based path)
2. Add tests for nested region path generation
3. Ensure build-time and run-time nesting both produce correct paths

**Deliverables**:
- Modified `src/Feature/Loader/RegionLoader.php` (summon changes)
- Modified `src/Chains/Path.php` (if using Option C)
- Integration tests for nested paths

### Phase 3: Event Bubbling (Optional)

1. Implement notification bubbling middleware
2. Add cycle detection to prevent infinite loops
3. Test with deeply nested hierarchies
4. Performance optimization for high-frequency changes

**Deliverables**:
- Modified `src/Chains/Notification.php` or new middleware
- Integration tests for event bubbling
- Performance benchmarks

### Phase 4: Process-Wrapper Integration

1. Add ContextChange subscription in process-wrapper
2. Broadcast context changes to dedicated socket
3. Update manifest to advertise context channel

**Deliverables**:
- Modified `machines/process-wrapper/holon.yml`
- New broadcast channel for context changes
- Client examples

## API Reference

### ContextChange Event

| Property | Type | Description |
|----------|------|-------------|
| `path` | `string` | Full region hierarchy path (e.g., "root/parent/child") |
| `key` | `string` | Context key that changed |
| `value` | `mixed` | New value after change |
| `previousValue` | `mixed` | Value before change (null if first set) |
| `timestamp` | `float` | microtime(true) when change occurred |

### Subscription Examples

```php
// Subscribe to context changes (requires SubscriptionFeature for type filtering)
$deregister = $region->on(function (ContextChange $change, ?Region $source) {
    printf(
        "[%s] %s.%s: %s → %s\n",
        date('H:i:s', (int)$change->timestamp),
        $change->path,
        $change->key,
        json_encode($change->previousValue),
        json_encode($change->value)
    );
});

// Later: unsubscribe
$deregister();
```

### YAML Configuration

```yaml
features:
  - class: Noem\State\Feature\ContextBroadcast\ContextBroadcastFeature
    # Previous value tracking is always enabled
    # Build-time bubbling via RECEIVE_EVENTS is automatic
```

## Serialization Control

### Problem

Complex objects in context may not serialize to JSON cleanly. We need mechanisms to:
1. Opt out of broadcasting for individual properties
2. Opt out of broadcasting for an entire region's context
3. Ensure only serializable data is broadcast

### Solution: Three-Level Control

The serialization control mechanism operates at three levels, evaluated in order:

```
$this->set('key', 'value')
    ↓
1. Region-level: Is broadcast disabled for this region? → Skip all
    ↓
2. Schema gate: Is JsonSchemaFeature loaded?
   - Yes: Does 'key' have a schema entry? → No entry = skip
   - No: Pass through (broadcast all by default)
    ↓
3. Property-level: Does schema entry have broadcast: false? → Skip
    ↓
4. Emit ContextChange event
```

### Level 1: Default Behavior (No JsonSchemaFeature)

When JsonSchemaFeature is NOT loaded, **all context changes are broadcast**. This provides the simplest default for basic use cases.

```php
// Without JsonSchemaFeature - all changes broadcast
->enableFeatures(
    new ExtendedState(),
    new ContextBroadcastFeature()
)
```

Users can still disable broadcasting entirely via region-level opt-out.

### Level 2: Schema-Based Broadcasting (With JsonSchemaFeature)

When JsonSchemaFeature IS loaded, **only properties with schema definitions are broadcast**. This treats schema-defined properties as "public API" and non-schema properties as internal implementation details.

```yaml
context:
  schema:
    - name: progress
      type: integer
      default: 0
      # 'progress' changes ARE broadcast (has schema)

states:
  - name: processing
    onEnter:
      - run: !php |
          return static function() {
              $this->set('progress', 50);        # Broadcast (in schema)
              $this->set('internalCache', [...]);  # NOT broadcast (no schema)
          };
```

**Rationale**:
- Schema-defined properties are explicitly typed → intended to be serializable
- Non-schema properties are internal → may contain closures, resources, circular refs
- Consistent with PresentationFeature which requires schema entries

### Level 3: Property-Level Opt-Out

Extend the schema with an optional `broadcast` flag to exclude specific properties:

```yaml
context:
  schema:
    - name: progress
      type: integer
      default: 0
      # broadcast: true (implicit default)

    - name: userToken
      type: string
      broadcast: false   # Sensitive - exclude from broadcast

    - name: sessionData
      type: object
      broadcast: false   # Complex object - might not serialize cleanly
```

**Use cases**:
- Sensitive data (tokens, passwords, API keys)
- Complex objects that are in schema for validation but shouldn't be broadcast
- Performance optimization for frequently-changing internal state

### Level 4: Region-Level Opt-Out

Disable broadcasting for an entire region's context:

```yaml
context:
  broadcast: false     # No context changes from this region are broadcast
  schema:
    - name: progress
      type: integer
```

**Use cases**:
- Internal helper regions that don't need external observability
- Performance-critical regions with high-frequency context changes
- Nested regions where only the parent should broadcast

### Configuration Summary

| Scenario | JsonSchemaFeature | `context.broadcast` | Property `broadcast` | Result |
|----------|-------------------|---------------------|---------------------|--------|
| No schema feature | Not loaded | (ignored) | (n/a) | **All broadcast** |
| Schema, no flags | Loaded | (default: true) | (default: true) | **Only schema properties** |
| Region opt-out | Loaded | `false` | (any) | **Nothing broadcast** |
| Property opt-out | Loaded | (true) | `false` | **Excluded property** |
| Mixed | Loaded | (true) | varies | **Per-property control** |

### Implementation: Schema Storage for Runtime Access

The broadcast configuration must be accessible at runtime. During build:

1. `AddJsonSchema` (existing) sets default values
2. **New**: Store the broadcast config in Meta under a separate type

```php
// In ContextBroadcastFeature build step
class AddBroadcastConfig implements BuildStep
{
    public function __construct(
        private readonly array $schema,
        private readonly bool $regionBroadcast = true
    ) {}

    public function callback(RegionBuilder $builder, callable $next, callable $first): Region
    {
        $meta = $builder->chainMail->get(Meta::class);
        $region = $next($builder);

        // Store broadcast config for runtime access
        $broadcastMeta = $meta->call(new Params\Meta($region, BroadcastConfigMetaType::get()));
        $broadcastMeta['enabled'] = $this->regionBroadcast;
        $broadcastMeta['properties'] = [];

        foreach ($this->schema as $entry) {
            $name = $entry['name'];
            // Default to true if not specified
            $broadcastMeta['properties'][$name] = $entry['broadcast'] ?? true;
        }

        return $region;
    }
}
```

### Implementation: Set Chain Middleware

```php
// In ContextBroadcastFeature
$setChain->link(function (Params\Set $set, callable $next) use ($meta, $notificationChain) {
    // 1. Check region-level opt-out
    $broadcastConfig = $meta->call(new Params\Meta($set->region, BroadcastConfigMetaType::get()));
    if (isset($broadcastConfig['enabled']) && $broadcastConfig['enabled'] === false) {
        return $next($set);  // Skip broadcast
    }

    // 2. Check schema gate (if config exists, we have JsonSchemaFeature)
    if (isset($broadcastConfig['properties'])) {
        // Only broadcast if property is in schema AND broadcast not disabled
        if (!isset($broadcastConfig['properties'][$set->key])) {
            return $next($set);  // Not in schema, skip
        }
        if ($broadcastConfig['properties'][$set->key] === false) {
            return $next($set);  // Explicitly disabled, skip
        }
    }

    // Get previous value
    $contextMeta = $meta->call(new Params\Meta($set->region, ContextMetaType::get()));
    $previousValue = $contextMeta[$set->key] ?? null;

    // Perform the set
    $result = $next($set);

    // Emit change event
    $change = new ContextChange(
        path: $set->region->path(),
        key: $set->key,
        value: $set->value,
        previousValue: $previousValue,
        timestamp: microtime(true),
    );

    $notifyParams = new Params\Notify($set->region, $change);
    foreach ($notificationChain->call($notifyParams) as $listener) {
        $listener($change, $set->region);
    }

    return $result;
}, prepend: false);
```

### Schema Extension for JsonSchemaFeature

Extend the existing schema validation to recognize `broadcast` flag:

```php
// In ContextBroadcastFeature (if JsonSchemaFeature loaded)
$schema?->link(function (SchemaContext $context, callable $next) {
    $contextSchema = $context->getCustomSchema('context');

    if ($contextSchema !== null) {
        // Extend to add broadcast flag at context level
        $contextSchema = $contextSchema->extend([
            'broadcast' => Expect::bool(true),  // Default: true
        ]);

        // Extend schema entries to add per-property broadcast flag
        // (This requires modifying JsonSchemaFeature's schema definition)
        $context->addCustomSchema('context', $contextSchema);
        $context->region = $context->region->extend([
            'context' => $contextSchema,
        ]);
    }

    return $next($context);
});
```

## Remaining Open Questions

1. **Batch API**: Is the `batch()` helper necessary, or is single-change notification sufficient for most use cases?

## Related Work

- **Redux**: Centralized state management with subscriber pattern
- **MobX**: Observable state with automatic dependency tracking
- **Vue Reactivity**: Proxy-based change detection
- **EventEmitter**: Node.js event pattern

## Appendix: Research Sources

### Files Examined

- `src/Feature/ExtendedState/ExtendedState.php` - Core feature implementation
- `src/Chains/Path.php` - Path chain implementation
- `src/Chains/Notification.php` - Notification chain
- `src/Chains/ConnectedRegions.php` - Region hierarchy
- `src/Feature/Loader/RegionLoader.php` - Summon implementation
- `src/Feature/OrthogonalRegions/OrthogonalRegions.php` - Build-time nesting
- `machines/process-wrapper/` - Broadcasting patterns

### Test Files

- `tests/PHPUnit/Integration/Feature/Loader/NestedRegionsTest.php`
- `tests/PHPUnit/Unit/Core/RegionBuilder/PathGenerationTest.php`