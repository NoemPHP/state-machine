# Context Broadcast Feature - Summary

**Status**: Implemented
**Created**: 2026-01-16
**Related**: [README.md](./README.md)

## Quick Reference

| Document | Purpose |
|----------|---------|
| [README.md](README.md) | Full proposal with architecture, API, and phases |
| [nested-regions-analysis.md](nested-regions-analysis.md) | Deep dive into nesting challenges |
| [process-wrapper-integration.md](process-wrapper-integration.md) | Process-wrapper socket broadcasting |

## The Goal

Enable external systems to observe state machine context changes in real-time, similar to subscribing to a Redux store:

```php
// External observer subscribes to context changes
$region->on(function (ContextChange $change) {
    echo "{$change->path}.{$change->key} = " . json_encode($change->value);
});

// Inside state machine callback
$this->set('progress', 75);  // Automatically emits ContextChange event
```

## Key Findings from Research

### 1. ExtendedState Architecture

The Set chain (`src/Chains/Set.php`) is the interception point. Current flow:

```
$this->set('key', 'value')
    → BoundAccess chain
    → Set chain middleware (ExtendedState.php:32-38)
    → Meta chain → Mesh storage
```

**No events are emitted.** Adding a second middleware to Set chain that emits `ContextChange` events is straightforward.

### 2. Path Chain Works for Static Nesting

The Path chain (`src/Chains/Path.php:17-30`) correctly builds hierarchical paths for regions connected via `OrthogonalRegions`:

```php
// Path chain walks up parent chain via ConnectedRegions
$parent = $this->parent($region);
while ($parent) {
    $base = $parent->currentState() . 'SUMMARY.md/' . $base;
    $parent = $this->parent($parent);
}
```

### 3. Summon Breaks Paths (The Tricky Part)

`summon()` returns a `Region` but **does not establish a connection**. The summoned region appears as a root with no parent.

**Current summon code** (RegionLoader.php):
```php
return Holon::fromYaml($resolvedPath, ['autoRun' => false]);
// No connect() call - child has no parent relationship
```

**Result**: `$summonedRegion->path()` returns only the child's state name, not the full hierarchy.

### 4. process-wrapper Demonstrates Broadcasting

The process-wrapper machine shows the pattern:
- Subscribe to inner holon events via `$innerHolon->on()`
- Serialize to JSON with newline delimiter
- Broadcast to Unix sockets (`BroadcastChannel`)
- Multiple channel types (stdout, stderr, interactions, logs)

## Design Decisions (Approved)

### Nested Region Path Strategy: Auto-Connect with Auto-Disconnect

Summoned regions are automatically connected to their parent for path tracking. When the region is unset from context or the owning coroutine finishes, the connection is automatically cleaned up.

**Implementation**:
- `summon()` calls `connect()` with `Connection::DYNAMIC` flag
- Track summoned regions in coroutine metadata
- On coroutine completion or context unset, call disconnect

### Event Bubbling: Build-Time Automatic, Run-Time Manual

- **Build-time nesting** (OrthogonalRegions): Events automatically bubble to parent regions via existing `RECEIVE_EVENTS` connection flag
- **Run-time nesting** (summon): Manual - implementation adds a subscription that forwards events if needed

This is trivial for run-time since you just add:
```php
$child->on(fn(ContextChange $e) => $parent->notificationChain->call(new Notify($parent, $e)));
```

### Previous Value Tracking: Always Enabled

Previous values are always tracked and included in `ContextChange` events. The extra read on every set is acceptable for the observability benefits.

### Implementation Location: New ContextBroadcastFeature

Separate feature that requires `ExtendedState`. Keeps concerns separated and allows opt-in.

## Proposed Implementation Order

### Phase 1: Basic Broadcasting (Low Risk)
1. Create `ContextChange` event class
2. Add middleware to Set chain
3. Emit via existing Notification chain
4. Tests for single-region broadcasting

**Files to create/modify**:
- `src/Feature/ExtendedState/ContextChange.php` (new)
- `src/Feature/ContextBroadcast/ContextBroadcastFeature.php` (new)
- `tests/PHPUnit/Unit/Feature/ContextBroadcast/` (new)

### Phase 2: Fix Summon Paths (Medium Risk)
1. Modify `summon()` to auto-connect with `DYNAMIC` flag
2. Verify Path chain works with summoned regions
3. Integration tests for nested paths

**Files to modify**:
- `src/Feature/Loader/RegionLoader.php`

### Phase 3: Event Forwarding (Higher Risk)
1. Optional bubbling to parent regions
2. Respect `RECEIVE_EVENTS` flag
3. Cycle detection

**Files to modify**:
- `src/Chains/Notification.php` or new middleware

### Phase 4: Process-Wrapper Integration
1. Add `context.sock` broadcast channel
2. Subscribe to inner holon's ContextChange events
3. Update manifest

**Files to modify**:
- `machines/process-wrapper/holon.yml`
- `machines/process-wrapper/src/RuntimeDirectory.php`

## Code Sketch: ContextChange Event

```php
<?php
namespace Noem\State\Feature\ExtendedState;

readonly class ContextChange implements \JsonSerializable
{
    public function __construct(
        public string $path,
        public string $key,
        public mixed $value,
        public mixed $previousValue,
        public float $timestamp,
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

## Code Sketch: Set Chain Middleware

```php
// In ContextBroadcastFeature
$setChain->link(function (Params\Set $set, callable $next) use ($meta, $notificationChain) {
    // Get previous value (if tracking enabled)
    $metaParams = new Params\Meta($set->region, ContextMetaType::get());
    $data = $meta->call($metaParams);
    $previousValue = $data[$set->key] ?? null;

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
}, prepend: false);  // Run AFTER storage
```

## Resolved Questions

### Debouncing
**Decision**: Out of scope for initial implementation. Left to consumers.
- Process-wrapper can implement debouncing if needed
- Feature stays simple, consumers add complexity as needed

### Serialization
**Decision**: Three-level control mechanism.

1. **Default (no JsonSchemaFeature)**: Broadcast all context changes
2. **With JsonSchemaFeature**: Only broadcast properties with schema entries (schema = public API)
3. **Property-level opt-out**: Add `broadcast: false` to schema entry
4. **Region-level opt-out**: Add `context.broadcast: false` to disable entirely

```yaml
context:
  broadcast: true     # Region-level (default: true)
  schema:
    - name: progress
      type: integer
      # broadcast: true (default)

    - name: userToken
      type: string
      broadcast: false   # Sensitive - exclude

    - name: internalCache
      type: object
      broadcast: false   # Complex - exclude
```

**Rationale**:
- Schema-defined properties are explicitly typed → serializable
- Non-schema properties are internal → may contain non-serializable objects
- Consistent with PresentationFeature pattern

## Remaining Open Question

1. **Batch API**: Is the `batch()` helper necessary, or is single-change notification sufficient?

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Performance degradation from notifications | Medium | Medium | Feature is opt-in |
| Breaking existing summon behavior | Low | Medium | Auto-connect uses minimal `DYNAMIC` flag only |
| Serialization failures for complex values | Low | Low | Schema-based filtering excludes non-schema properties |
| Connection leak on coroutine failure | Medium | Medium | Track in coroutine metadata, cleanup on completion |

## Next Steps

1. **Create specs** - Use spec-planner to create formal specifications
2. **Implement** - Use core-development-expert for red-green implementation