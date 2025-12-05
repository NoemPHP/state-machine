# Core Development Skill - PHP Library Development Protocol

## ⚡ CRITICAL CORE PRINCIPLES

**Chain system uses LIFO** - Last registered middleware executes FIRST. Feature order matters. ExtendedState MUST precede AsyncFeature.

---

## 🚫 ABSOLUTE RULES - NEVER VIOLATE

| Rule | Violation = Consequence |
|------|-------------------------|
| **ExtendedState BEFORE AsyncFeature** | STOP → Reorder features → Schema error resolved |
| **ChainMail service type = key** | STOP → Fix return type annotation |
| **Guards MUST accept trigger parameter** | STOP → Add `fn(object $trigger)` |
| **NEVER modify /vendor/** | STOP → Ask user for approval |
| **ConfigAccessor: NEVER override __construct()** | STOP → Use initialize() instead |

---

## 🏗️ CORE COMPONENTS REFERENCE

### Region.php

**State machine runtime. Handles:**
- State storage and transitions
- Event dispatch and action execution
- Connected region management
- Lifecycle callbacks (onEnter, onExit, on)

**Critical methods:**

```php
public function trigger(object $payload): void;              // Dispatch event
public function isInState(string $state): bool;              // State check
public function isFinal(): bool;                             // Final state check
public function connect(Region $childRegion): void;          // Hierarchical composition
```

**FIXED BUG**: Connected regions now correctly update state via `processOneAction()`.

### RegionBuilder.php

**Fluent API for constructing regions.**

**Key methods:**
```php
->enableFeatures(...$features)      // Register feature middleware
->setStates(...$names)              // Define state names
->markInitial($state)               // Designate initial state
->markFinal($state)                 // Designate final state
->on($state, $event, $callback)    // Register action
->onEnter($state, $callback)        // Enter callback
->onExit($state, $callback)         // Exit callback
->addBuildStep(BuildStep $step)     // Custom build logic
->build(array $args = [])           // Construct region
```

### Chain System

**LIFO (Last-In-First-Out) execution.**

```php
$chain = new Chain();
$chain->link($middleware1);  // Registered first
$chain->link($middleware2);  // Registered second
$chain->link($middleware3);  // Registered last

// Execution order: middleware3 → middleware2 → middleware1 → provider
```

**WHY LIFO:**
- Last middleware wraps all previous middleware
- Creates "Russian doll" pattern
- Outer layers intercept/modify inner layers

**Standard pattern:**

```php
$chain = new Chain($defaultProvider);
$chain->link(function($context, callable $next) {
    // Pre-processing
    $result = $next($context);  // Call inner layers
    // Post-processing
    return $result;
});
```

### ChainMail (DI Container)

**PSR-11 container with middleware-based configuration.**

**Protocol:**

```
STEP 1: Create ChainMail
  └─ $chainMail = new ChainMail();

STEP 2: Supply services
  └─ $chainMail->supply(fn(): MyService => new MyService());

STEP 3: Register middleware
  └─ $chainMail->use(function(RegionBuilder $b, callable $next) { ... });

STEP 4: Execute middleware
  └─ $chainMail->boot();

STEP 5: Retrieve services
  └─ $service = $chainMail->get(MyService::class);
```

**CRITICAL PATTERN - Service Type = Key:**

**WRONG:**
```php
$chainMail->supply(fn(): object => new MyService());
$service = $chainMail->get(MyService::class);  // FAILS: Service not found
```

**CORRECT:**
```php
$chainMail->supply(fn(): MyService => new MyService());
$service = $chainMail->get(MyService::class);  // SUCCESS
```

### Mesh (Helper Registry)

**Helper function registry with ArrayAccess.**

```php
$mesh = new Mesh();
$mesh['helper'] = fn() => 'value';

// Access via ArrayAccess
if (isset($mesh['helper'])) {
    $value = $mesh['helper']();
}
```

**WRONG:**
```php
$mesh->hasHelper('helper');  // NO such method - use isset()
```

**CORRECT:**
```php
isset($mesh['helper'])  // Use isset() for checking
```

---

## 🎯 FEATURE SYSTEM PROTOCOL

### Feature Interface

**MANDATORY implementation:**

```php
interface Feature
{
    public function __invoke(ChainMail $chainMail): void;
}
```

### Feature Implementation Pattern

**Standard structure:**

```php
<?php

namespace Noem\State\Feature\MyFeature;

use Noem\State\Chains\ChainMail;
use Noem\State\Feature\Feature;

class MyFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        // STEP 1: Supply services
        $this->supplyServices($chainMail);

        // STEP 2: Register middleware
        $this->registerMiddleware($chainMail);
    }

    private function supplyServices(ChainMail $chainMail): void
    {
        $chainMail->supply(fn(): MyService => new MyService());
    }

    private function registerMiddleware(ChainMail $chainMail): void
    {
        $chainMail->use(function(RegionBuilder $builder, callable $next) {
            // Modify builder before build
            $builder->onEnter('*', fn($t) => $this->logEnter($t));
            return $next($builder);
        });
    }
}
```

### Feature Dependencies & Ordering

**CRITICAL - Feature order matters due to LIFO execution.**

```
Features enabled as: [A, B, C]
Execute as: A wraps (B wraps (C wraps provider))
Flow: A → B → C → provider → C → B → A
```

**Dependency Matrix:**

| Feature | MUST Come After | Reason |
|---------|----------------|--------|
| `AsyncFeature` | `ExtendedState` | Extends `context` schema |
| Any feature | `RegionLoader` | Loader processes YAML/array first |

**CORRECT ORDER:**

```php
$builder->enableFeatures(
    new RegionLoader(),      // Always first for YAML/array loading
    new ExtendedState(),     // Creates base 'context' schema
    new AsyncFeature()       // Extends existing 'context' schema
);
```

**WRONG ORDER (CAUSES ERROR):**

```php
$builder->enableFeatures(
    new AsyncFeature(),      // Tries to extend non-existent schema
    new ExtendedState()      // Creates schema too late
);
// Result: "Unexpected item 'context › resolvers'" validation error
```

---

## 📋 CONFIG ACCESSOR PATTERN

### Problem

**Fragile nested array access:**

```php
// ❌ OLD: Fragile, verbose, error-prone
if (!isset($context['loader']['array']['context']['resolvers'])) {
    return $builder;
}
$resolvers = $context['loader']['array']['context']['resolvers'];
```

### Solution

**Typed config accessors with fluent API:**

```php
// ✅ NEW: Clean, type-safe, discoverable
$asyncConfig = $context->config(AsyncConfig::class);
if (!$asyncConfig->hasResolvers()) {
    return $builder;
}
foreach ($asyncConfig->resolvers() as $resolver) {
    // ...
}
```

### Creating Config Accessors

**MANDATORY TEMPLATE:**

```php
<?php

namespace Noem\State\Feature\MyFeature\Config;

use Noem\State\Chains\Params\Config\ConfigAccessor;

/**
 * Typed accessor for MyFeature configuration.
 */
class MyFeatureConfig extends ConfigAccessor
{
    // ⚠️ DO NOT override __construct() - it's final!
    // ✅ Override initialize() instead

    protected function initialize(): void
    {
        // Optional: initialization logic
        // e.g., build caches, validate config
    }

    /**
     * Get widgets from loader config.
     *
     * @return array<string, mixed>
     */
    public function widgets(): array
    {
        return $this->get('loader.array.my_feature.widgets', []);
    }

    /**
     * Check if widgets configured.
     */
    public function hasWidgets(): bool
    {
        return $this->has('loader.array.my_feature.widgets');
    }

    /**
     * Require specific widget or throw.
     *
     * @throws \RuntimeException
     */
    public function requireWidget(string $name): array
    {
        return $this->require("loader.array.my_feature.widgets.{$name}");
    }
}
```

**Base Methods (in ConfigAccessor):**
- `get(string $path, mixed $default = null): mixed` - Get value at dot path
- `has(string $path): bool` - Check if path exists
- `require(string $path): mixed` - Get or throw exception

**Usage in Feature:**

```php
private function processConfig(Chains\EnhanceRegionBuilder $enhanceRegionBuilder): void
{
    $enhanceRegionBuilder->link(function (Params\BuildParams $context, callable $next) {
        $builder = $next($context);

        $config = $context->config(MyFeatureConfig::class);
        if (!$config->hasWidgets()) {
            return $builder;
        }

        foreach ($config->widgets() as $widget) {
            // Process widgets...
        }

        return $builder;
    });
}
```

**Benefits:**
- ✅ Type-safe with PHPDoc for IDE autocomplete
- ✅ Discoverable - IDE shows all config methods
- ✅ Isolated - Each feature owns config domain
- ✅ Testable - Mock ConfigAccessor in tests
- ✅ Consistent - Same pattern across features
- ⚠️ Final constructor - Prevents signature breaking

**Existing Accessors:**
- `AsyncConfig` - Async feature resolver definitions
- `LoaderConfig` - Common loader operations

---

## 🔗 MIDDLEWARE CHAINS REFERENCE

### Chain Types in RegionBuilder

```php
private Chains\Chain $featureChain;              // Feature registration
private Chains\Chain $buildParamsChain;          // Build context initialization
private Chains\Chain $enhanceRegionBuilderChain; // Builder modification
private Chains\Chain $produceRegion;             // Region construction
```

### Chain Usage Pattern

```
STEP 1: Create chain with default provider
  └─ $chain = new Chain(fn($context) => defaultBehavior($context));

STEP 2: Link middleware (executed LIFO)
  └─ $chain->link(function($context, callable $next) {
         // Pre-processing
         $context = modifyContext($context);
         // Call inner layers
         $result = $next($context);
         // Post-processing
         return enhanceResult($result);
     });

STEP 3: Execute chain
  └─ $result = $chain->call($initialContext);
```

### EnhanceRegionBuilder Chain

**Most features add middleware here:**

```php
$chainMail->use(function(RegionBuilder $builder, callable $next) {
    // Get chain from builder
    $chain = $builder->getChain(Chains\EnhanceRegionBuilder::class);

    // Link middleware
    $chain->link(function(BuildParams $context, callable $next) {
        $builder = $next($context);

        // Modify builder based on config
        $config = $context->config(MyFeatureConfig::class);
        foreach ($config->widgets() as $widget) {
            $builder->onEnter($widget['state'], $widget['callback']);
        }

        return $builder;
    });

    return $next($builder);
});
```

---

## 🚨 CRITICAL IMPLEMENTATION DETAILS

### Connected Regions State Update (FIXED)

**Previously broken. Now fixed:**

```php
// In Region::processOneAction()
foreach ($connections as $childRegion) {
    $childRegion->processOneAction($payload);  // Now properly:
    //   1. Calls action chain
    //   2. Updates $childRegion->currentState
    //   3. Calls DoTransition chain
}
```

**Benefit**: All hierarchical state machines work correctly.

### Execution Order

**Actions execute BEFORE transitions:**

```
$region->trigger($event);

Order:
1. Action callbacks fire
2. Guard evaluation
3. Transition execution (if guard passes)
4. onEnter callbacks for new state
```

**Verification test:**

```php
$sequence = [];
$region
    ->on('A', 'move', fn($t) => $sequence[] = 'action')
    ->addTransition('A', 'B', fn($t): bool => ($sequence[] = 'guard') && true)
    ->onEnter('B', fn($t) => $sequence[] = 'enter:B');

$region->trigger((object)['type' => 'move']);
$this->assertEquals(['action', 'guard', 'enter:B'], $sequence);
```

### Spawn Behavior

**Guards control CREATION, not propagation:**

- `guard = true` → NEW child spawned + receives trigger
- `guard = false` → NO new spawn, but EXISTING children receive trigger
- Once spawned, children ALWAYS receive events (unless DYNAMIC flag + predicate)

### AsyncFeature Task Lifecycle

**Tasks cleaned up at START of action dispatch:**

```php
// In deferTicksUntilActionComplete():
// 1. Manual cleanup BEFORE callbacks
// 2. Removes finished tasks from callbackTaskMap
// 3. Prevents race conditions
```

**AVOID**: Multiple cleanup mechanisms create race conditions.

**Task Identity**: WeakMap uses callback as key - manage sequential tasks carefully.

### Call::call Buffer Pattern

**`Call::call` returns generator's return value, NOT yielded values:**

**CORRECT:**
```php
$buffer = [];
yield Call::call(new Load($file), $buffer);
$content = implode('', $buffer);  // Concatenate yielded characters
```

**WRONG:**
```php
$content = yield Call::call(new Load($file));  // Returns null (metadata)
```

**WHY**: `StreamHandler` yields characters but returns wrapper_data metadata. Buffer parameter is intended API for collecting I/O results.

---

## 💻 CODE CONVENTIONS

### File Headers

```php
<?php

declare(strict_types=1);

namespace Noem\State\Feature\MyFeature;
```

### Namespace Structure

```
Noem\State\
├── Region.php, RegionBuilder.php          # Core
├── Chains\                                 # Chain system
│   ├── Chain.php
│   ├── ChainMail.php
│   ├── Mesh.php
│   └── Params\                            # Chain parameters
├── Feature\                                # Features
│   ├── Feature.php                        # Interface
│   ├── Transitions\                       # TransitionsFeature
│   ├── Loader\                            # RegionLoader
│   ├── ExtendedState\                     # ExtendedState feature
│   └── Async\                             # AsyncFeature
└── Middleware\                            # Legacy (being phased out)
```

### PHP Version & Features

**Minimum: PHP 8.4+**

**USE modern features:**

```php
// Union types
public function process(User|Admin $actor): Result;

// Property promotion
public function __construct(
    private readonly string $name,
    private readonly ?array $config = null,
) {}

// Match expressions
$result = match($status) {
    'idle' => $this->handleIdle(),
    'busy' => $this->handleBusy(),
    default => throw new \LogicException(),
};

// Null-safe operator
$value = $config?->get('key');
```

---

## 🎛️ AVAILABLE FEATURES REFERENCE

### TransitionsFeature

**Automatic transitions based on guards.**

```php
use Noem\State\Feature\Transitions\{AddTransition, TransitionsFeature};

$region = (new RegionBuilder())
    ->enableFeatures(new TransitionsFeature())  // Enabled by default
    ->addBuildStep(new AddTransition('A', 'B', fn($t): bool => $t->ready))
    ->build();
```

**Key behaviors:**
- Checks transitions after each action
- Skips when state changed imperatively
- Prevents transitions from final states
- Waits for connected regions to finish
- First-match-wins guard evaluation

### ExtendedState

**Context data scoped to states/regions.**

```php
$region = (new RegionBuilder())
    ->enableFeatures(new ExtendedState())
    ->build(['loader' => ['array' => [
        'context' => [
            'counter' => 0,
            'items' => [],
        ]
    ]]]);

// Access context
$context = $region->getContext();
$counter = $context['counter'];
```

### AsyncFeature

**Coroutine-based async operations.**

**REQUIRES ExtendedState first:**

```php
$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),  // MANDATORY before AsyncFeature
        new AsyncFeature()
    )
    ->build(['loader' => ['array' => [
        'context' => [
            'resolvers' => [
                'fetchData' => [
                    'handler' => 'service.dataFetcher',
                    'cacheKey' => 'myData',
                ]
            ]
        ]
    ]]]);
```

**Capabilities:**
- Lazy loading with cache
- Cache invalidation
- Task lifecycle management
- WeakMap-based task tracking

### RegionLoader

**Load from YAML/array configs.**

```php
use Noem\State\Feature\Loader\RegionLoader;

$loader = new RegionLoader();
$builder = $loader->fromYaml($yamlContent);
$region = $builder->build();
```

### Holon (SelfContainedLoader)

**⚠️ Implementation exists, being renamed from `SelfContainedLoader`**

**Complete machine bootstrap from YAML:**

```php
// One-liner
$region = Holon::fromYaml('machine.yaml');

// With auto-run
$result = Holon::fromYaml('machine.yaml', [
    'autoRun' => true,
    'maxIterations' => 1000,
]);
```

**Capabilities:**
- Two-phase bootstrap (parse → build)
- PSR-11 container construction
- Feature instantiation from class names
- Integrated event loop
- Helper functions (php, env, service)

**Pending work:**
- Rename to Holon
- Implement machine spawning via `spawn.machine`
- Add container delegation support

---

## 🚫 COMMON ERRORS & SOLUTIONS

| Error | Cause | Solution |
|-------|-------|----------|
| `Service 'X' not found` | Wrong return type | Use `fn(): MyType => ...` |
| `Required Parameter 0 not declared` | Guard missing trigger | Add `fn(object $trigger): bool => true` |
| `Unexpected item 'context › resolvers'` | Wrong feature order | Enable `ExtendedState` before `AsyncFeature` |
| `Undefined constant MetaType::Region` | Wrong MetaType | Use `ContextMetaType::get()` |
| Tests fail after "working" change | Specs define contract | Fix code, not tests |

---

## 📚 KEY CLASSES REFERENCE

**Core:**
- `src/Region.php` - State machine runtime
- `src/RegionBuilder.php` - Builder API
- `src/Chains/Chain.php` - LIFO middleware
- `src/Chains/ChainMail.php` - DI container
- `src/Feature/Feature.php` - Feature interface
- `src/Chains/Params/Config/ConfigAccessor.php` - Config accessor base

**Documentation:**
- `README.md` - Project overview
- `CLAUDE.md` - Bootstrap context
- `BUG_CONNECTED_REGIONS_STATE_UPDATE.md` - Hierarchical state fix
- `SPAWN_INTEGRATION_PROPOSAL.md` - Machine spawning design

---

## 🔗 SKILL INTEGRATION

**Load with these skills:**
- **specification** - Spec-driven workflow
- **testing** - Testing patterns
- **region-development** - Usage examples
- **documentation** - Document changes
