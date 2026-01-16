# Async Feature - Unimplemented Functionality

**Status**: Draft
**Created**: 2025-12-28

## Overview

AsyncFeature currently implements 177/199 specs. The remaining 22 specs document planned functionality that extends the async system with advanced control flow and lazy resolution capabilities.

---

## 1. Meta Chain Hooks for Resolver Evaluation

**Spec**: `feature-registration` (lines 37-40)

### Current State
AsyncFeature does not currently install middleware on the meta chain.

### Planned Implementation
AsyncFeature should hook into the meta chain to intercept mesh property access and trigger lazy resolver evaluation. This enables automatic async property resolution when context properties are accessed.

**Key Behaviors**:
- Install middleware on meta chain during feature registration
- Intercept mesh `offsetGet` operations
- Check resolver registry for matching key
- Trigger ornament creation and resolution on first access

---

## 2. Event-Driven Call Helpers

**Spec**: `call-helpers` (lines 398-411)

### Implemented
- `waitForSecs` - Time-based delays
- `call` - Nested coroutine composition
- `fork` - Fire-and-forget task spawning
- `cancel` - Task termination

### Unimplemented

#### `take(matcher)`
Pauses task until a matching event occurs via subscription system.

**Behavior**:
- Accepts event matcher (type filter)
- Creates temporary subscription filtered by matcher
- Pauses current task
- When matching event arrives, resumes task with event payload
- Auto-unsubscribes after first match (one-shot pattern)

**Use Case**: Wait for specific trigger or state change before continuing

#### `takeAny(matchers[])`
Pauses task until any of multiple event types match.

**Behavior**:
- Accepts array of matchers
- Creates subscription for each matcher
- Resumes on first match from any subscription
- Cancels remaining subscriptions after first match

**Use Case**: Wait for one of several possible events (race condition)

#### `wager(matcher, ?replacement)`
Registers task cancellation trigger on matching event.

**Behavior**:
- Accepts event matcher
- Creates persistent subscription (not one-shot)
- When event matches, cancels current task
- Optionally spawns replacement task
- Subscription persists until task completes or is cancelled

**Use Case**: Interrupt-driven patterns, timeouts, cancellation on error signals

---

## 3. Lazy Resolver System (Ornament)

**Spec**: `ornament-resolution` (lines 418-465)

### Architecture
`Ornament` extends `Mesh` with lazy evaluation middleware for computed properties.

### Unimplemented Components

#### Ornament Class
Wraps mesh with lazy resolution capability.

**Key Methods**:
- `offsetGet($key)` - Intercepts property access, triggers resolution if needed
- `offsetSet($key, $value)` - Invalidates dependent caches
- `offsetUnset($key)` - Invalidates dependent caches

**State Tracking**:
- `resolvedValues: array` - Caches computed results
- `dependencies: array` - Tracks which keys each resolver accessed
- `resolving: string|null` - Prevents infinite recursion

#### OrnamentResolver
Context object passed to resolver callbacks.

**API**:
```php
$resolver = function(OrnamentResolver $ctx) {
    $value = $ctx->get('dependency'); // Access triggers dependency tracking
    $result = compute($value);
    $ctx->resolve($result);           // Signal completion
};
```

**Methods**:
- `get(string $key): mixed` - Read mesh property, track as dependency
- `has(string $key): bool` - Check property existence
- `resolve(mixed $value): void` - Complete resolution with computed value

#### Resolution Flow
1. `Ornament::offsetGet('computed')` called
2. Check if cached → return cached value
3. Check if resolving → prevent recursion, return null
4. Set resolving flag
5. Look up resolver from registry
6. Create OrnamentResolver context
7. Invoke resolver with context
8. Track accessed dependencies during execution
9. Cache resolved value
10. Clear resolving flag
11. Return value

#### Invalidation
When `offsetSet` or `offsetUnset` modifies a tracked key:
1. Find all resolvers that depend on modified key
2. Clear cached values for those resolvers
3. Next access will trigger re-resolution

---

## 4. Resolver Registry

**Spec**: `resolver-registry` (lines 466-498)

### Architecture
Global registry mapping `(Region, key) → ResolverRecord`.

### Unimplemented Components

#### Resolvers Service
Singleton service registered in ChainMail container.

**API**:
```php
class Resolvers {
    public function register(Region $region, string $key, callable $resolver, ?AsyncConfig $config = null): void;
    public function get(Region $region, string $key): ?ResolverRecord;
    public function has(Region $region, string $key): bool;
    public function getAllForRegion(Region $region): array;
}
```

**Storage**:
- `WeakMap<Region, array<string, ResolverRecord>>` - Per-region resolver maps
- Weak references allow garbage collection when region destroyed

#### ResolverRecord
Immutable data structure encapsulating resolver metadata.

**Properties**:
```php
readonly class ResolverRecord {
    public function __construct(
        public Region $region,
        public string $key,
        public callable $resolver,
        public ?AsyncConfig $config = null
    ) {}
}
```

---

## 5. Resolver Integration

**Spec**: `resolver-integration` (lines 787-839)

### Meta Chain Hook Implementation
When meta chain is accessed (via `$region->getContextMeta()`):

1. **Check Initialization**: Has region already been initialized for ornaments?
2. **Retrieve Resolvers**: Get all resolvers for current region from registry
3. **Create Ornaments**: For each resolver, create ornament wrapper
4. **Wrap in Generator**: Resolver execution wrapped in async generator:
   ```php
   function wrapResolver($resolver, $config) {
       return function() use ($resolver, $config) {
           $task = $scheduler->enqueue($resolver, $config);
           while (!$task->isFinished()) {
               yield;
           }
           return $task->getReturn();
       };
   }
   ```
5. **Register with Ornament**: Ornament receives wrapped generator as resolver
6. **Mark Initialized**: Store in observer to prevent re-initialization

### Resolver Processing
Resolvers should be processed through prepare/invoke chains like regular callbacks:
- Parameter injection via ExtendedState
- Access to context helpers
- Middleware transformations

### AsyncConfig Application
ResolverRecord stores optional AsyncConfig:
- **Priority**: High-priority resolvers execute more steps per tick
- **Singleton**: Prevents concurrent resolution of same property
- **Timeout**: Cancels runaway resolver tasks

---

## 6. Replace Operations (I/O Extensions)

**Spec**: `replace-operations` (lines 1041-1063)

### Context Helpers

#### `replace(filepath, search, replacement)`
Literal string replacement with streaming (constant memory).

**Behavior**:
- Opens file for reading
- Creates temp file for writing
- Streams line-by-line or chunk-by-chunk
- Replaces exact string matches
- Atomic rename (temp → original)
- Yields during processing

#### `regexReplace(filepath, pattern, replacement)`
Regex-based replacement with capture group support.

**Behavior**:
- Same streaming approach as `replace`
- Applies `preg_replace` to each chunk
- Supports backreferences (`$1`, `$2`, etc.)
- Handles multi-line patterns correctly

### Abilities (Agent Tools)

#### `replace` Ability
Exposes `replace()` as AI agent tool.

**Schema**:
```json
{
  "type": "object",
  "properties": {
    "filepath": {"type": "string"},
    "search": {"type": "string"},
    "replacement": {"type": "string"}
  },
  "required": ["filepath", "search", "replacement"]
}
```

#### `regex-replace` Ability
Exposes `regexReplace()` as AI agent tool.

**Schema**:
```json
{
  "type": "object",
  "properties": {
    "filepath": {"type": "string"},
    "pattern": {"type": "string"},
    "replacement": {"type": "string"}
  },
  "required": ["filepath", "pattern", "replacement"]
}
```

---

## Implementation Sequence Recommendation

### Phase 1: Resolver Registry & Records
1. Implement `Resolvers` service class
2. Implement `ResolverRecord` data structure
3. Register service in AsyncFeature
4. Write registration/retrieval tests

### Phase 2: Ornament Resolution
1. Implement `Ornament` class extending Mesh
2. Implement `OrnamentResolver` context
3. Implement dependency tracking
4. Implement cache invalidation
5. Prevent infinite loops
6. Write resolution flow tests

### Phase 3: Resolver Integration
1. Add meta chain hook in AsyncFeature
2. Implement ornament creation for region resolvers
3. Wrap resolvers in async generators
4. Process resolvers through chains
5. Apply AsyncConfig to resolver tasks
6. Write integration tests

### Phase 4: Event-Driven Call Helpers
1. Implement `take()` with single matcher
2. Implement `takeAny()` with multiple matchers
3. Implement `wager()` with cancellation
4. Test subscription cleanup
5. Test event correlation
6. Write integration tests

### Phase 5: Replace Operations
1. Implement streaming `replace()` I/O class
2. Implement streaming `regexReplace()` I/O class
3. Add context helpers via BoundAccess
4. Register abilities for agent invocation
5. Write streaming and correctness tests

---

## Testing Strategy

### Unit Tests
- Each component in isolation
- Mock dependencies (scheduler, registry, mesh)
- Test edge cases (empty, recursion, errors)

### Integration Tests
- Full resolution workflow
- Multi-resolver dependencies
- Concurrent resolver access
- Event-driven call patterns
- Replace operations on real files

### Spec Compliance
- Each spec → one test class
- Test class name matches acceptance criteria
- Tests verify contract, not implementation details

---

## Notes for Implementation Session

1. **Start with Registry**: Foundation for everything else
2. **Ornament is Complex**: Dependency tracking requires careful state management
3. **WeakMap Usage**: Critical for memory management, test with GC
4. **Call Helpers are Independent**: Can be implemented in parallel with resolvers
5. **Replace Ops are Standalone**: Low coupling, good for separate task

**Total Estimated Specs to Implement**: 22

**Current Progress**: 177/199 (88.9%)
**Target**: 199/199 (100%)
