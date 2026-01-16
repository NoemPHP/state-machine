# Context Broadcast - Nested Regions Analysis

**Status**: Implemented
**Created**: 2026-01-16
**Related**: [README.md](./README.md)

## Overview

This document provides a deep technical analysis of how nested regions work and the challenges for context broadcasting.

## Current State of Nested Regions

### Build-Time Nesting (OrthogonalRegions)

**How it works**: The `OrthogonalRegions` feature extends the YAML schema to allow `regions:` arrays within state definitions.

**Connection establishment** (from `ProcessArray.php:46-62`):

```php
$subRegion = $builder->newInstance()->build([
    'loader' => ['array' => $data],
]);

$builder->connect(
    $subRegion,
    Connection::DYNAMIC | Connection::RECEIVE_EVENTS | Connection::RECEIVE_ACTIONS | Connection::RECEIVE_META,
    fn(Connection $c) => $c->local->currentState() === $state
);
```

**Key characteristics**:
1. Child gets **fresh builder instance** (`newInstance()`)
2. Connection is **predicate-based** - active only when parent in specific state
3. Connection flags: `DYNAMIC | RECEIVE_EVENTS | RECEIVE_ACTIONS | RECEIVE_META`
4. The `ConnectedRegions` chain tracks this relationship
5. `Path::parent()` can find the parent via `ConnectedRegions`

**Result**: `$childRegion->path()` correctly returns `"parentState/childState"`.

### Run-Time Nesting (Summon)

**How it works**: The `summon()` helper loads a machine from YAML and returns a `Region` instance.

**Current implementation** (from `RegionLoader.php:378-381`):

```php
// CRITICAL: Pass autoRun: false to prevent summoned machines from running immediately
// The parent machine will control execution via Runtime
return Holon::fromYaml($resolvedPath, ['autoRun' => false]);
```

**Key characteristics**:
1. Returns `Region` directly (not wrapped in Runtime)
2. **NO automatic connection** to parent region
3. Parent stores region in context and controls execution manually
4. The summoned region has **no knowledge of its parent**

**Result**: `$summonedRegion->path()` only returns the summoned region's state (e.g., `"initial"`) with **no parent prefix**.

## The Path Chain in Detail

### Implementation (from `Path.php:14-31`)

```php
public function __construct(private readonly ConnectedRegions $connectedRegions)
{
    // Provider: returns current state name
    parent::__construct(fn(Region $r) => $r->currentState());

    // Single middleware: walks up parent chain
    $this->link(function (Region $region, callable $next) {
        $base = $next($region);  // Get state name from provider
        $parent = $this->parent($region);  // Find parent via ConnectedRegions
        while ($parent) {
            $base = $parent->currentState() . 'nested-regions-analysis.md/' . $base;
            $parent = $this->parent($parent);
        }
        return $base;
    });
}

public function parent(Region $childRegion): ?Region
{
    // Uses ConnectedRegions chain to find parent
    $list = $this->connectedRegions->call(new Connection($childRegion, false, 0));
    if (empty($list)) {
        return null;
    }
    if (count($list) > 2) {
        throw new \RuntimeException('There cannot be more than one parent of a region');
    }
    return $list[0];
}
```

### Why Summon Breaks Paths

The `parent()` method queries `ConnectedRegions` for regions where:
- The queried region is the **remote** (child) side
- `searchMode = false` (looking for parents, not children)

Since `summon()` doesn't call `$builder->connect()`, there's no connection record. The summoned region appears as a **root region** with no parent.

## Connection System Deep Dive

### Connection Flags

```php
// From Connection.php
public const RECEIVE_ACTIONS = 1;   // Child receives dispatched actions
public const RECEIVE_EVENTS = 2;    // Child receives event notifications
public const RECEIVE_META = 4;      // Child inherits parent's metadata (context)
public const DYNAMIC = 8;           // Connection can be toggled by predicate
```

### ConnectedRegions Chain (from `ConnectedRegions.php`)

The chain maintains a registry of all connections:

```php
class ConnectedRegions extends Chain
{
    private array $connections = [];

    public function connect(Region $local, Region $remote, int $flags, ?callable $predicate = null): void
    {
        $this->connections[] = new ConnectionEntry($local, $remote, $flags, $predicate);
    }

    // Provider searches for matching connections
    // searchMode=true: find children (local matches)
    // searchMode=false: find parents (remote matches)
}
```

### What Connections Enable

| Flag | Effect |
|------|--------|
| `RECEIVE_ACTIONS` | When parent triggers action, child also receives it |
| `RECEIVE_EVENTS` | When parent emits notification, child also receives it |
| `RECEIVE_META` | Child's Mesh inherits from parent's Mesh (via `extendWith()`) |
| `DYNAMIC` | Connection only active when predicate returns true |

## Approved Solution: Auto-Connect with Auto-Disconnect

### Implementation in RegionLoader.php

```php
private function registerSummonHelper(BoundAccess $boundAccess, ...): void
{
    $boundAccess->link(function (BoundAccessParams $params, callable $next) {
        if ($params->name !== 'summon') {
            return $next($params);
        }

        $path = $params->payload[0];
        $resolvedPath = $this->resolveSummonPath($path, $buildParams);

        // Load the child region
        $childRegion = Holon::fromYaml($resolvedPath, ['autoRun' => false]);

        // Establish parent-child connection for path tracking
        $parentRegion = $params->region;
        $disconnect = $this->connectedRegions->connect(
            $parentRegion,
            $childRegion,
            Connection::DYNAMIC,  // Minimal flags - just for path tracking
            fn() => true          // Always active once summoned
        );

        // Track for auto-disconnect (see below)
        $this->trackSummonedRegion($parentRegion, $childRegion, $disconnect);

        return $childRegion;
    });
}
```

### Auto-Disconnect Mechanism

Summoned regions must be disconnected when:
1. The region is unset from context
2. The owning coroutine finishes (completes or errors)
3. The parent region is destroyed

**Coroutine Tracking**:

```php
private \SplObjectStorage $summonedByCoroutine;  // Maps Task → [disconnect callbacks]

private function trackSummonedRegion(
    Region $parent,
    Region $child,
    callable $disconnect
): void {
    // Get current coroutine/task from AsyncFeature
    $currentTask = $this->getCurrentTask($parent);

    if ($currentTask) {
        if (!$this->summonedByCoroutine->contains($currentTask)) {
            $this->summonedByCoroutine[$currentTask] = [];
        }
        $this->summonedByCoroutine[$currentTask][] = $disconnect;

        // Register cleanup on task completion
        $currentTask->onComplete(function() use ($currentTask) {
            $this->cleanupSummonedRegions($currentTask);
        });
    }
}

private function cleanupSummonedRegions(Task $task): void
{
    if ($this->summonedByCoroutine->contains($task)) {
        foreach ($this->summonedByCoroutine[$task] as $disconnect) {
            $disconnect();
        }
        $this->summonedByCoroutine->detach($task);
    }
}
```

### Connection Flags

Only `Connection::DYNAMIC` is used for summon connections:
- **NOT** `RECEIVE_ACTIONS`: Parent controls execution manually
- **NOT** `RECEIVE_EVENTS`: Build-time regions get this, run-time regions manage manually
- **NOT** `RECEIVE_META`: Context inheritance is explicit for summoned regions

This ensures summon behavior is minimally affected - only path tracking is added.

## Approved Event Bubbling Strategy

### Build-Time: Automatic via RECEIVE_EVENTS

For regions created via `OrthogonalRegions`, the `RECEIVE_EVENTS` connection flag is already set. The Notification chain should forward `ContextChange` events to parent regions when this flag is present.

```php
// In Notification chain or ContextBroadcastFeature
$notificationChain->link(function (Params\Notify $notify, callable $next) use ($connectedRegions) {
    $listeners = $next($notify);  // Local listeners first

    // Bubble ContextChange events to parent if RECEIVE_EVENTS is set
    if ($notify->event instanceof ContextChange) {
        $parentConnections = $connectedRegions->call(
            new Connection($notify->region, false, Connection::RECEIVE_EVENTS)
        );

        foreach ($parentConnections as $parent) {
            $parent->notificationChain->call(
                new Params\Notify($parent, $notify->event)
            );
        }
    }

    return $listeners;
});
```

### Run-Time: Manual Subscription Forwarding

For summoned regions, event bubbling is explicitly added by the implementation:

```php
->onEnter('processing', function(object $t): \Generator {
    $child = $this->summon('child.yml');
    yield;

    // Manually forward context changes to parent if desired
    $child->on(fn(ContextChange $e) =>
        $this->region->notificationChain->call(new Notify($this->region, $e))
    );

    // ... use child ...
})
```

This is trivial to implement and keeps run-time nesting behavior explicit.

## Implementation Plan

### Phase 1: Basic Broadcasting

1. Create `ContextChange` event class with previous value tracking
2. Add middleware to Set chain to emit events
3. Events emit on source region
4. Path includes full hierarchy

### Phase 2: Summon Auto-Connect with Auto-Disconnect

1. Modify `summon()` to establish connection with `DYNAMIC` flag
2. Track summoned regions per coroutine
3. Cleanup connections on coroutine completion
4. Path chain works via `ConnectedRegions`

### Phase 3: Build-Time Event Bubbling

1. Add middleware to bubble `ContextChange` via `RECEIVE_EVENTS` connections
2. Cycle detection (region cannot be its own ancestor)
3. Test with multi-level OrthogonalRegions

## Test Scenarios

### Scenario 1: Single Region

```
Region: root
Context: { counter: 0 }
Action: $this->set('counter', 1)
Expected Event:
  ContextChange {
    path: "root",
    key: "counter",
    value: 1,
    previousValue: 0
  }
```

### Scenario 2: Build-Time Nested (OrthogonalRegions)

```
Structure:
  parent (state: "active")
    └── child (state: "running")

Action (in child): $this->set('status', 'done')

Expected Event:
  ContextChange {
    path: "active/running",
    key: "status",
    value: "done",
    previousValue: null
  }
```

### Scenario 3: Run-Time Nested (Summon)

```
Structure:
  parent (state: "processing")
    └── summoned child (state: "initial")

Action (in summoned child): $this->set('result', 42)

Expected Event:
  ContextChange {
    path: "processing/initial",  // Requires auto-connect
    key: "result",
    value: 42,
    previousValue: null
  }
```

### Scenario 4: Deep Nesting

```
Structure:
  grandparent (state: "level1")
    └── parent (state: "level2")
        └── child (state: "level3")

Action (in child): $this->set('deep', true)

Expected Event:
  ContextChange {
    path: "level1/level2/level3",
    key: "deep",
    value: true,
    previousValue: null
  }
```

## Edge Cases and Mitigations

| Edge Case | Risk | Mitigation |
|-----------|------|------------|
| **Summoned region re-summoned** | Old connection orphaned | Each summon tracked per coroutine, cleanup on completion |
| **Circular connections** | Path infinite loop | Path chain already checks `count($list) > 2` throws error |
| **Connection predicate false** | N/A | Summon uses `fn() => true` always-active predicate |
| **Region destroyed** | Connection leak | Auto-disconnect on coroutine completion |
| **High-frequency updates** | Performance | Left to consumer to implement debouncing if needed |
| **Coroutine fails mid-execution** | Connection leak | Task onComplete fires on error too, triggers cleanup |