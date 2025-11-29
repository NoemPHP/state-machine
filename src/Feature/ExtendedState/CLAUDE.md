# ExtendedState Feature - Context Management for State Machines

## Purpose

ExtendedState provides a **context system** that allows state machine callbacks to access and modify shared state using `$this->get()`, `$this->set()`, and `$this->dispatch()` methods. It binds all event handlers to a `Bound` object that acts as their execution context.

**Key Value**: Enables stateful behavior in state machines without manual context management or passing state through event payloads.

## Public API

All state machine callbacks (actions, guards, transitions, lifecycle hooks) automatically have access to these methods via `$this`:

### Core Methods

```php
// Get context value
$value = $this->get(string $key): mixed

// Set context value
$this->set(string $key, mixed $value): void

// Dispatch event to current region
$this->dispatch(object $event): void
```

### Magic Access

```php
// Property access (uses __get/__set)
$value = $this->propertyName;
$this->propertyName = 'new value';

// Method calls (uses __call)
// Note: Only works if middleware is registered for that method name
$result = $this->customMethod($arg1, $arg2);
```

### String Conversion

```php
// Get region path
echo (string)$this;  // Returns region path like "parent.child"
```

## Usage Patterns

### Basic Context Access

```php
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;

$region = (new RegionBuilder())
    ->enableFeatures(new ExtendedState())
    ->setStates('idle', 'processing', 'done')
    ->onEnter('idle', function(object $t) {
        // Set initial context
        $this->set('counter', 0);
        $this->set('items', []);
    })
    ->onAction('processing', function(object $t) {
        // Read and modify context
        $counter = $this->get('counter');
        $this->set('counter', $counter + 1);
    })
    ->build();
```

### YAML Configuration

When combined with RegionLoader, context can be defined in YAML:

```yaml
context:
  counter: 0
  items: []
  config:
    maxRetries: 3
    timeout: 5000
```

Access in callbacks:

```php
$maxRetries = $this->get('config')['maxRetries'];
// or with loader config accessor pattern
$config = $context->config(MyFeatureConfig::class);
```

### Self-Dispatch Pattern

Callbacks can trigger events on their own region:

```php
->onEnter('processing', function(object $t) {
    $retries = $this->get('retries');
    if ($retries >= 3) {
        // Dispatch internal event to self
        $this->dispatch((object)['type' => 'max_retries']);
    }
})
```

## Integration Points

### With RegionLoader

ExtendedState hooks into `LoaderChains\Schema` to add a `context` section to the YAML schema:

```php
// In ExtendedState::__invoke()
$schema?->link(function (SchemaContext $context, callable $next) {
    $contextSchema = Expect::structure([]);
    $context->addCustomSchema('context', $contextSchema);
    // ...
});
```

This enables the `context:` key in YAML machine definitions.

### With AsyncFeature

**CRITICAL**: ExtendedState MUST be loaded BEFORE AsyncFeature:

```php
// ✅ CORRECT
->enableFeatures(
    new ExtendedState(),  // First
    new AsyncFeature()    // Second - extends context schema
)

// ❌ WRONG - AsyncFeature will fail
->enableFeatures(
    new AsyncFeature(),   // Tries to extend non-existent schema
    new ExtendedState()   // Too late
)
```

**Reason**: AsyncFeature adds `resolvers` to the `context` schema. If ExtendedState hasn't created the base schema yet, AsyncFeature throws a validation error.

### With Hierarchical Regions

Context data inherits from parent regions when connected with `Connection::RECEIVE_META`:

```php
$parent = (new RegionBuilder())
    ->enableFeatures(new ExtendedState())
    ->build();

$child = (new RegionBuilder())
    ->enableFeatures(new ExtendedState())
    ->build();

$parent->connect($child, Connection::RECEIVE_META);

// In parent callback:
$this->set('shared', 'value');

// In child callback:
$value = $this->get('shared');  // Returns 'value'
```

The `Meta` chain handles this inheritance via the `Mesh::extendWith()` mechanism (src/Chains/Meta.php:76-116).

## Architecture

### Component Overview

```
ExtendedState (Feature)
    ├── Bound (Context object - the "$this" in callbacks)
    │   ├── __get() → BoundAccess chain → Get chain
    │   ├── __set() → BoundAccess chain → Set chain
    │   └── __call() → BoundAccess chain
    │
    ├── BoundAccess (Chain for method/property resolution)
    │   └── Default handlers: get(), set(), dispatch()
    │
    ├── ContextMetaType (Metadata type identifier)
    │
    └── Middleware registrations:
        ├── PrepareInvokable - Binds callbacks to Bound
        ├── Get - Retrieves values from Meta chain
        ├── Set - Stores values in Meta chain
        └── Schema - Adds 'context' to YAML schema
```

### Callback Binding Mechanism

ExtendedState intercepts ALL callbacks through the `PrepareInvokable` chain:

```php
// From ExtendedState.php:63-86
$boundCallbackMap = new \SplObjectStorage();
$prepareInvokable->link(
    function (Params\Callback $callback, callable $next) use ($boundCallbackMap, $contextStorage) {
        if (!$boundCallbackMap->contains($callback->handler)) {
            $closure = ($callback->handler)(...);
            $region = $callback->region;

            // Create one Bound instance per region
            if (!$contextStorage->contains($region)) {
                $contextStorage[$region] = new Bound($region, $boundAccess);
            }

            // Bind callback to Bound instance
            $bound = $closure->bindTo($contextStorage[$region]);
            $boundCallbackMap->offsetSet($callback->handler, $bound);
        }

        $callback->handler = $boundCallbackMap->offsetGet($callback->handler);
        return $next($callback);
    }
);
```

**Key Points**:
- One `Bound` instance per region (stored in SplObjectStorage)
- Each callback is bound once and cached
- Binding happens automatically - no user intervention needed
- `$this` in callbacks becomes the `Bound` instance

### Data Storage via Meta Chain

Context data is stored using the `Meta` chain with `ContextMetaType`:

```php
// Get operation (ExtendedState.php:42-53)
$getChain->link(
    function (Params\Get $get, callable $next) use ($meta) {
        $metaParams = new Params\Meta($get->region, ContextMetaType::get());
        $data = $meta->call($metaParams);  // Returns Mesh

        if (isset($data[$get->key])) {
            return $data[$get->key];
        }

        return $next($get);  // Fall through to next middleware
    }
);

// Set operation (ExtendedState.php:32-38)
$setChain->link(
    function (Params\Set $set, callable $next) use ($meta) {
        $metaParams = new Params\Meta($set->region, ContextMetaType::get());
        $data = $meta->call($metaParams);  // Get Mesh for this region
        $data[$set->key] = $set->value;     // Store in Mesh

        return $next($set);
    }
);
```

**Storage hierarchy**:
1. `Meta` chain manages per-region metadata
2. `ContextMetaType` identifies this feature's data
3. Data stored in `Mesh` (implements ArrayAccess)
4. Mesh supports inheritance via `extendWith()`

## Critical Idiosyncrasies

### 1. Feature Loading Order

**MANDATORY**: ExtendedState MUST come before AsyncFeature in feature list.

```php
// ✅ CORRECT
->enableFeatures(new ExtendedState(), new AsyncFeature())

// ❌ FAILS with "Unexpected item 'context › resolvers'"
->enableFeatures(new AsyncFeature(), new ExtendedState())
```

**Why**: Features execute in LIFO order during build. AsyncFeature's schema middleware runs BEFORE ExtendedState's if loaded first, trying to extend a non-existent schema.

**Reference**: See core-development skill, line 204-220.

### 2. Bound Instance Scope

Each region gets ONE `Bound` instance, shared by ALL callbacks in that region:

```php
// Both callbacks share the same $this
->onEnter('idle', function(object $t) {
    $this->set('flag', true);
})
->onAction('idle', function(object $t) {
    // Same $this, sees 'flag' = true
    $flag = $this->get('flag');
})
```

**Implication**: Setting properties on `$this` affects all callbacks in that region.

### 3. Property Access Error Handling

Accessing non-existent properties/methods throws `RuntimeException`:

```php
// Throws: "Property 'nonExistent' not found in callback context"
$value = $this->nonExistent;

// Throws: "Method 'nonExistent' not found in callback context"
$result = $this->nonExistent();
```

**Why**: The BoundAccess chain's default provider throws when no middleware handles the request (BoundAccess.php:17-22).

**Best Practice**: Always use `$this->get()` with defaults:

```php
// ✅ Safe
$value = $this->get('key', 'default');

// ❌ Unsafe if 'key' might not exist
$value = $this->key;
```

### 4. Loader Config vs Runtime Context

Two different namespaces:

```php
// 1. Loader config (build-time, immutable)
$region = $builder->build([
    'loader' => [
        'array' => [
            'context' => [
                'initial' => 'value'
            ]
        ]
    ]
]);

// 2. Runtime context (accessed via $this->get/set)
->onEnter('idle', function(object $t) {
    // This accesses RUNTIME context, not build config
    $this->set('counter', 0);
})
```

**Loader config** is processed during build by features. **Runtime context** is accessed during execution via Bound methods.

### 5. SplObjectStorage Caching

Bound instances are cached in `SplObjectStorage`:

```php
// From ExtendedState.php:76-78
if (!$contextStorage->contains($region)) {
    $contextStorage[$region] = new Bound($region, $boundAccess);
}
```

**Implication**: Region instances are used as keys. If you somehow clone or recreate a region, it gets a NEW Bound instance with SEPARATE context.

### 6. Meta Chain Inheritance

When regions are connected with `Connection::RECEIVE_META`, the child's Mesh extends the parent's:

```php
// From Meta.php:106-111
if (!$metaData->contains($parentRegion) || !$metaData->contains($metaParams->region)) {
    $parentMetaParams = new Params\Meta($parentRegion, ContextMetaType::get());
    $parent = $first($parentMetaParams);
    $child = $next($metaParams);
    $parent->extendWith($child);  // Child inherits from parent
}
```

**Behavior**:
- Child reads parent values via Mesh inheritance
- Child writes are LOCAL only
- Parent changes visible to child
- Child changes NOT visible to parent

### 7. Dispatch is Self-Only

`$this->dispatch()` triggers events on the CURRENT region only:

```php
// From ExtendedState.php:130-135
case 'dispatch':
    $args = $params->payload;
    $trigger = array_shift($args);
    $params->region->trigger($trigger, true);  // Triggers on $params->region
    return null;
```

It does NOT propagate to parent or connected regions. For that, use `$region->trigger()` directly.

### 8. No Direct Context Access

There's NO public API to access context from outside a callback:

```php
// ❌ NO API for this:
$contextValue = $region->getContext('key');

// ✅ Must use callback:
$value = null;
$region->trigger((object)[
    'getContext' => fn() => $value = $this->get('key')
]);
```

**Workaround**: Store references in closures or trigger events to extract values.

## Common Patterns

### Stateful Guards

```php
->addBuildStep(new AddTransition('counting', 'done', function(object $t): bool {
    $count = $this->get('count', 0);
    return $count >= 10;
}))
->onAction('counting', function(object $t) {
    $count = $this->get('count', 0);
    $this->set('count', $count + 1);
})
```

### Accumulation Pattern

```php
->onAction('processing', function(object $t) {
    $items = $this->get('items', []);
    $items[] = $t->data;
    $this->set('items', $items);
})
```

### Computed Properties

```php
->onAction('state', function(object $t) {
    $firstName = $this->get('firstName');
    $lastName = $this->get('lastName');
    $this->set('fullName', "$firstName $lastName");
})
```

### Error Tracking

```php
->onAction('processing', function(object $t) {
    try {
        // ... risky operation
    } catch (\Exception $e) {
        $errors = $this->get('errors', []);
        $errors[] = $e->getMessage();
        $this->set('errors', $errors);

        $this->dispatch((object)['type' => 'error']);
    }
})
```

## Testing Patterns

### Asserting Context Values

```php
// From RegionBuilderTestCase
protected function assertRegionContext(
    Region $region,
    string $key,
    mixed $expectedValue
): void {
    $actualValue = null;

    // Extract via callback
    $extractCallback = function(object $t) use ($key, &$actualValue) {
        $actualValue = $this->get($key);
    };

    // Bind and execute
    $bound = /* ... bind to region's Bound ... */;
    $extractCallback->call($bound, new \stdClass());

    $this->assertEquals($expectedValue, $actualValue);
}
```

### Mocking Bound Context

For unit tests, you can't easily mock `$this` since binding happens in middleware. Instead:

1. Test at integration level with real ExtendedState
2. OR test the middleware chains directly with mock BuildParams

## When to Use ExtendedState

### ✅ Use When

- State machines need to accumulate data across events
- Guards need to evaluate based on accumulated state
- Callbacks need to coordinate via shared variables
- Building self-contained machines from YAML definitions
- Implementing stateful protocols or workflows

### ❌ Avoid When

- Data can be passed via trigger payload
- Callbacks are pure and stateless
- External storage (database, cache) is more appropriate
- Context would duplicate trigger data unnecessarily

## Relationship to Other Features

| Feature | Relationship | Notes |
|---------|--------------|-------|
| **AsyncFeature** | Depends on ExtendedState | Must load ExtendedState FIRST |
| **TemplateFeature** | Uses context for variables | Templates can reference `$this->get()` values |
| **AiFeature** | Uses context for prompts | AI prompts can include context data |
| **RegionLoader** | Provides YAML `context:` | ExtendedState hooks into loader schema |
| **TransitionsFeature** | Independent | Guards can use `$this->get()` when ExtendedState loaded |
| **Holon** | Uses for self-contained bootstrap | Holon machines have isolated context |

## Files Reference

| File | Purpose |
|------|---------|
| `src/Feature/ExtendedState/ExtendedState.php` | Main feature implementation |
| `src/Feature/ExtendedState/Bound.php` | Context object (`$this` in callbacks) |
| `src/Feature/ExtendedState/BoundAccess.php` | Method/property resolution chain |
| `src/Feature/ExtendedState/ContextMetaType.php` | Metadata type identifier |
| `src/Feature/ExtendedState/ContextChains/Params/BoundAccessParams.php` | Parameters for BoundAccess chain |
| `src/Chains/Get.php` | Get value chain |
| `src/Chains/Set.php` | Set value chain |
| `src/Chains/Meta.php` | Metadata storage chain |
| `src/Chains/PrepareInvokable.php` | Callback preparation chain |

## Summary Checklist

When working with ExtendedState:

- [ ] Load ExtendedState BEFORE AsyncFeature
- [ ] Use `$this->get(key, default)` for safe access
- [ ] Remember `$this` is `Bound`, not your class
- [ ] Context is per-region, shared by all callbacks
- [ ] Loader `context:` is build-time config, `$this->get/set` is runtime
- [ ] Parent context visible to children via RECEIVE_META connection
- [ ] No direct context access from outside callbacks
- [ ] `$this->dispatch()` is self-only, doesn't propagate
- [ ] Missing properties/methods throw RuntimeException

---

**Last Updated**: 2025-11-28
**Feature Status**: Stable, production-ready
**Spec Coverage**: Integrated into loader/async/template/ai specs
