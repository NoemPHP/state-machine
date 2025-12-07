# Subscription Infrastructure Proposal - FINAL

**Date:** 2025-12-05
**Status:** FINAL - Global Listener Management Pattern
**Author:** Claude Code (AI Assistant)

---

## Executive Summary

Create **notification infrastructure** with global listener management:

1. NotificationChain provides **`subscribe($listener)`** method for global registration
2. Chain stores **ALL listeners globally** in a simple array (not per-Region)
3. Chain provider returns all listeners
4. Features **inspect parameter types directly** on returned listeners
5. Listeners receive **Region as optional second parameter** to identify event source
6. **SplObjectStorage<callable, string>** caches parameter types for performance
7. **BoundAccess integration** allows emitting from state callbacks

### Pattern

```php
// Subscribe globally on the chain
$deregister = $notificationChain->subscribe(function(MyEvent $event, ?Region $region = null) {
    // Handle event - Region parameter identifies source (useful for nested regions)
    echo "Event from: " . $region?->currentState();
});

// Unsubscribe when done
$deregister();

// Resolve listeners (returns ALL listeners globally)
$listeners = $notificationChain->call(new Notify($region, $event));

// Caller executes with Region context
foreach ($listeners as $listener) {
    $listener($event, $region);  // Region identifies event source
}
```

---

## Architecture

### Core Components

**1. NotificationChain::subscribe($listener)** - Global registration API (no Region parameter)
**2. array<callable>** - Simple global listener array storage
**3. Chain Provider** - Returns all listeners globally
**4. Notify Parameter** - Context for resolution (contains Region + event)
**5. Region::on()** - Convenience wrapper for subscribe()
**6. SubscriptionFeature** - Inspects parameter types directly on returned listeners
**7. SplObjectStorage<callable, string>** - Listener-level type cache (NOT per-Region listeners)
**8. BoundAccess Integration** - Emit events from within state callbacks

### How It Works

```
NotificationChain::subscribe($listener)
    ↓ adds to global listeners[] array
    ↓ returns $deregisterFunc that removes from array

NotificationChain::call(new Notify($region, $event))
    ↓ provider returns ALL listeners globally
    ↓ (middleware can transform)
    ↓ SubscriptionFeature inspects parameter types on each listener
    ↓ uses SplObjectStorage<callable,string> cache for types
    ↓ returns filtered array

Caller
    ↓ executes with $listener($event, $region)
    ↓ Region parameter lets listener identify event source

BoundAccess (optional)
    ↓ provides NotificationChain access in state callbacks
    ↓ allows emitting events from state logic
```

### Key Principle: Global Listeners

**Listeners are GLOBAL** - they hear events from ALL regions. This enables:
- Event bubbling from nested regions
- Cross-region communication
- Observer patterns across machine hierarchy

**Region parameter** lets listeners filter by source if needed:
```php
$chain->subscribe(function(MyEvent $e, ?Region $r = null) {
    if ($r === $specificRegion) {
        // Only handle events from specific region
    }
});
```

---

## Core Implementation

### 1. Notification Chain

**File:** `src/Chains/Notification.php`

```php
<?php

declare(strict_types=1);

namespace Noem\State\Chains;

use Noem\State\Chains\Params\Notify;
use Noem\State\Middleware\Chain;

/**
 * Chain that manages and resolves global event listeners
 *
 * Stores listeners globally (not per-Region).
 * Provider returns all listeners.
 * Features can filter based on type, priority, etc.
 *
 * @template-extends Chain<Notify,array>
 */
class Notification extends Chain
{
    /**
     * @var list<callable> Global listener storage
     */
    private array $listeners = [];

    public function __construct()
    {
        parent::__construct(
            provider: function(Notify $context): array {
                // Return ALL listeners globally
                return $this->listeners;
            }
        );
    }

    /**
     * Register a listener globally
     *
     * Stores listener in global array (not per-Region).
     * Listeners receive ($event, $region) when invoked.
     *
     * @param callable $listener Callback receiving (object $event, ?Region $region)
     * @return callable Deregister function
     */
    public function subscribe(callable $listener): callable
    {
        // Add listener to global array
        $this->listeners[] = $listener;

        // Return deregister function
        return function() use ($listener): void {
            $key = array_search($listener, $this->listeners, true);

            if ($key !== false) {
                unset($this->listeners[$key]);
                $this->listeners = array_values($this->listeners); // Re-index
            }
        };
    }
}
```

### 2. Notify Context Parameter

**File:** `src/Chains/Params/Notify.php`

```php
<?php

declare(strict_types=1);

namespace Noem\State\Chains\Params;

use Noem\State\Region;

/**
 * Context for listener resolution
 *
 * Carries both Region and event.
 * Region identifies event source for listeners.
 */
class Notify
{
    public function __construct(
        public readonly Region $region,
        public readonly object $event,
    ) {}
}
```

### 3. Region::on() Method

**File:** `src/Region.php` (modifications)

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
        private readonly Chains\Notification $notificationChain,  // NEW
    ) {
        $this->currentState = $initial;
    }

    /**
     * Register a listener for notifications (globally)
     *
     * Convenience wrapper for NotificationChain::subscribe().
     * Listeners receive event as first param, Region as optional second.
     * Listeners are GLOBAL - they receive events from all regions.
     *
     * @param callable $listener Callback (object $event, ?Region $region)
     * @return callable Deregister function
     */
    public function on(callable $listener): callable
    {
        // Delegate to chain's subscribe method
        return $this->notificationChain->subscribe($listener);
    }
}
```

### Core Usage (Without Feature)

```php
// Core only - returns all listeners globally, no filtering
$region = (new RegionBuilder())
    ->setStates('idle')
    ->build();

// Register listeners globally
$deregister1 = $region->on(function(object $event, ?Region $region = null) {
    echo "Listener 1 received event\n";
});

$deregister2 = $region->on(function(object $event, ?Region $region = null) {
    echo "Listener 2 received event\n";
});

// Application resolves and executes
$listeners = $region->notificationChain->call(new Notify($region, $myEvent));
// Returns: [listener1, listener2] (ALL listeners globally)

foreach ($listeners as $listener) {
    $listener($myEvent, $region);  // Pass Region to identify source
}
// Output: Listener 1 received event
//         Listener 2 received event

// Deregister
$deregister1();  // Removes listener 1 globally
```

**Use case:** Simple global listener collection without filtering.

---

## Feature Implementation

### SubscriptionFeature

**File:** `src/Feature/Subscription/SubscriptionFeature.php`

```php
<?php

declare(strict_types=1);

namespace Noem\State\Feature\Subscription;

use Noem\State\Chains\ChainMail;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Feature;
use Noem\State\RegionBuilder;
use Noem\State\Util\ParameterDeriver;

/**
 * Filters listeners by parameter type
 *
 * Inspects returned listeners directly and filters based on first parameter
 * type compatibility with the event payload. Uses SplObjectStorage for caching.
 */
class SubscriptionFeature implements Feature
{
    /**
     * @var \SplObjectStorage<callable, string> Listener type cache
     */
    private \SplObjectStorage $typeCache;

    public function __construct()
    {
        $this->typeCache = new \SplObjectStorage();
    }

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use($this->addTypeFiltering(...));
    }

    /**
     * Hook notification chain to filter listeners by type
     */
    private function addTypeFiltering(
        RegionBuilder $builder,
        callable $next
    ): RegionBuilder {
        $notificationChain = $builder->getChain(Notification::class);

        // Hook chain to filter listeners
        $notificationChain->link(function(Notify $context, callable $next) {
            // Get all listeners from provider/inner middleware
            $allListeners = $next($context);

            // Filter by type compatibility
            return array_filter($allListeners, function($listener) use ($context) {
                $expectedType = $this->getListenerType($listener);

                // Accept if type matches or is 'object' (catch-all)
                return $expectedType === 'object' || $context->event instanceof $expectedType;
            });
        }, prepend: true);  // Run first to filter before returning

        return $next($builder);
    }

    /**
     * Get expected type for listener (with caching)
     *
     * Inspects first parameter of callable and caches result.
     *
     * @param callable $listener
     * @return string Class name or 'object'
     */
    private function getListenerType(callable $listener): string
    {
        // Check cache first
        if (isset($this->typeCache[$listener])) {
            return $this->typeCache[$listener];
        }

        // Extract expected event type from first parameter
        try {
            $expectedType = ParameterDeriver::getParameterType($listener, 0);
        } catch (\Throwable $e) {
            $expectedType = 'object';  // Accept all
        }

        // Cache and return
        $this->typeCache[$listener] = $expectedType;
        return $expectedType;
    }
}
```

### BoundAccess Integration (Optional)

When ExtendedState feature is enabled, BoundAccess can provide NotificationChain access within state callbacks:

**File:** `src/Feature/Subscription/SubscriptionFeature.php` (enhancement)

```php
/**
 * Hook notification chain to filter listeners by type
 */
private function addTypeFiltering(
    RegionBuilder $builder,
    callable $next
): RegionBuilder {
    $notificationChain = $builder->getChain(Notification::class);

    // ... existing filtering code ...

    // Optional: Integrate with BoundAccess if ExtendedState is enabled
    if ($builder->hasFeature(ExtendedState::class)) {
        $this->integrateWithBoundAccess($builder, $notificationChain);
    }

    return $next($builder);
}

/**
 * Make NotificationChain available in BoundAccess
 *
 * Allows state callbacks to emit events via $this->notificationChain
 */
private function integrateWithBoundAccess(
    RegionBuilder $builder,
    Notification $notificationChain
): void {
    // Add notificationChain to BoundAccess context
    $builder->extendBoundAccess([
        'notificationChain' => $notificationChain,
    ]);
}
```

**Usage in state callbacks:**

```php
$builder->state('processing', [
    'entry' => function() {
        // Access NotificationChain from BoundAccess
        $listeners = $this->notificationChain->call(
            new Notify($this->region, new ProcessStarted())
        );

        foreach ($listeners as $listener) {
            $listener(new ProcessStarted(), $this->region);
        }
    }
]);
```

### How Applications Emit

**Pattern:**

```php
// Application feature emits events
class MyLoggingFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use(function(
            DispatchAction $dispatchAction,
            Notification $notificationChain,
            callable $next
        ) {
            $dispatchAction->link(function(Action $action, callable $next) use ($notificationChain) {
                $result = $next($action);

                // Resolve listeners
                $listeners = $notificationChain->call(
                    new Notify($action->region, $myEvent)
                );

                // Execute with error isolation, passing Region context
                foreach ($listeners as $listener) {
                    try {
                        $listener($myEvent, $action->region);
                    } catch (\Throwable $e) {
                        error_log("Listener failed: " . $e->getMessage());
                    }
                }

                return $result;
            });

            return $next($dispatchAction);
        });
    }
}
```

---

## Usage Examples

### Example 1: Core Only (No Filtering)

```php
// Without feature - returns all listeners globally
$region = (new RegionBuilder())
    ->setStates('idle')
    ->build();

$region->on(function(object $e, ?Region $r = null) {
    echo "A received event\n";
});

$region->on(function(object $e, ?Region $r = null) {
    echo "B received event\n";
});

// Resolve (returns ALL listeners)
$listeners = $region->notificationChain->call(new Notify($region, $event));
// Returns: [listenerA, listenerB]

// Execute with Region context
foreach ($listeners as $l) {
    $l($event, $region);
}
// Output: A received event
//         B received event
```

### Example 2: With Feature (Type Filtering)

```php
use Noem\State\Feature\Subscription\SubscriptionFeature;

class MyEvent { public string $data; }
class OtherEvent { public int $value; }

$region = (new RegionBuilder())
    ->enableFeatures(new SubscriptionFeature())
    ->setStates('idle')
    ->build();

// Type-specific listener
$region->on(function(MyEvent $e, ?Region $r = null) {
    echo "MyEvent: {$e->data}\n";
});

// Catch-all listener
$region->on(function(object $e, ?Region $r = null) {
    echo "Any event\n";
});

// Resolve for MyEvent
$myEvent = new MyEvent();
$myEvent->data = "test";

$listeners = $region->notificationChain->call(
    new Notify($region, $myEvent)
);
// Returns: [myEventListener, catchAllListener]  (filtered by feature)

// Execute
foreach ($listeners as $l) {
    $l($myEvent, $region);
}
// Output: MyEvent: test
//         Any event

// Resolve for OtherEvent
$listeners = $region->notificationChain->call(
    new Notify($region, new OtherEvent())
);
// Returns: [catchAllListener]  (MyEvent listener filtered out)

// Execute
foreach ($listeners as $l) {
    $l(new OtherEvent(), $region);
}
// Output: Any event
```

### Example 3: Application Emits with Error Isolation

```php
// Application decides when to emit
$chainMail->use(function(
    DispatchAction $da,
    Notification $nc,
    callable $next
) {
    $da->link(function($action, $next) use ($nc) {
        $result = $next($action);

        // Resolve
        $listeners = $nc->call(new Notify($action->region, $myEvent));

        // Execute with error isolation and Region context
        foreach ($listeners as $listener) {
            try {
                $listener($myEvent, $action->region);
            } catch (\Throwable $e) {
                // Isolate errors
                error_log($e->getMessage());
            }
        }

        return $result;
    });

    return $next($da);
});
```

### Example 4: Global Listeners with Region Filtering

```php
// Parent region
$parent = (new RegionBuilder())
    ->enableFeatures(new SubscriptionFeature())
    ->setStates('active')
    ->build();

// Child region
$child = (new RegionBuilder())
    ->enableFeatures(new SubscriptionFeature())
    ->setStates('working')
    ->build();

// Global listener that filters by Region parameter
$parent->on(function(WorkComplete $e, ?Region $region = null) use ($parent, $child) {
    if ($region === $parent) {
        echo "Parent completed work\n";
    } elseif ($region === $child) {
        echo "Child completed work\n";
    }
});

// When parent emits
$listeners = $parent->notificationChain->call(
    new Notify($parent, new WorkComplete())
);
foreach ($listeners as $l) {
    $l(new WorkComplete(), $parent);
}
// Output: Parent completed work

// When child emits (SAME listener hears it because global)
$listeners = $child->notificationChain->call(
    new Notify($child, new WorkComplete())
);
foreach ($listeners as $l) {
    $l(new WorkComplete(), $child);
}
// Output: Child completed work
```

### Example 5: BoundAccess Integration

```php
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Subscription\SubscriptionFeature;

class ProcessStarted {}

$region = (new RegionBuilder())
    ->enableFeatures(
        new ExtendedState(),
        new SubscriptionFeature()  // Auto-integrates with BoundAccess
    )
    ->state('processing', [
        'entry' => function() {
            // Emit event from within state callback
            $listeners = $this->notificationChain->call(
                new Notify($this->region, new ProcessStarted())
            );

            foreach ($listeners as $listener) {
                $listener(new ProcessStarted(), $this->region);
            }
        }
    ])
    ->build();

// Subscribe to events globally
$region->on(function(ProcessStarted $e, ?Region $r = null) {
    echo "Process started in state: " . $r?->currentState() . "\n";
});

// Trigger entry callback
$region->trigger(new SomeEvent());
// Output: Process started in state: processing
```

---

## Implementation Plan

### Phase 1: Core

**Files:**
- `src/Chains/Notification.php` - Chain with subscribe() and global array storage
- `src/Chains/Params/Notify.php` - Context
- `src/Region.php` - Add on() method

**Specs:**
```yaml
# specs/chain/notification.yaml
features:
  - name: listener-management
    specs:
      - acceptanceCriteria: NotificationChain stores listeners globally in array
        criticality: contract
        intent: Enables global listener storage outside Region, supporting event bubbling and cross-region communication
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chains/Notification/GlobalStorageTest.php

      - acceptanceCriteria: NotificationChain.subscribe() adds listener to global array
        criticality: contract
        intent: Provides simple registration API for adding listeners globally without Region context
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chains/Notification/SubscribeAddsListenerTest.php

      - acceptanceCriteria: NotificationChain.subscribe() returns deregister function
        criticality: contract
        intent: Enables cleanup through returned closure that removes listener from global array when invoked
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chains/Notification/DeregisterFunctionTest.php

      - acceptanceCriteria: Deregister function removes listener from global array
        criticality: contract
        intent: Ensures listeners can be unregistered cleanly, preventing memory leaks and unwanted event notifications
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chains/Notification/DeregisterRemovesListenerTest.php

  - name: listener-resolution
    specs:
      - acceptanceCriteria: Notification chain provider returns all listeners globally
        criticality: contract
        intent: Chain call retrieves all registered listeners regardless of Region, enabling event bubbling patterns
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chains/Notification/ProviderReturnsAllListenersTest.php

      - acceptanceCriteria: Notification chain returns empty array when no listeners registered
        criticality: constraint
        intent: Ensures safe default behavior when no listeners exist, preventing null/undefined errors
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chains/Notification/EmptyArrayDefaultTest.php

      - acceptanceCriteria: Listeners can be filtered by middleware before being returned
        criticality: contract
        intent: Chain middleware can transform listener array, enabling type filtering and priority sorting
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Chains/Notification/MiddlewareCanFilterTest.php
```

```yaml
# specs/core/region.yaml (add new feature)
  - name: notification-subscription
    specs:
      - acceptanceCriteria: Region.on() delegates to NotificationChain.subscribe()
        criticality: contract
        intent: Provides convenient listener registration API that delegates to chain's subscribe method
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Core/Region/OnDelegatesToSubscribeTest.php

      - acceptanceCriteria: Region.on() returns deregister function from chain
        criticality: contract
        intent: Exposes chain's deregister function through Region API for listener cleanup
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Core/Region/OnReturnsDeregisterTest.php

      - acceptanceCriteria: Listeners receive Region as optional second parameter
        criticality: contract
        intent: Enables listeners to identify event source Region, supporting filtering and nested region patterns
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Core/Region/ListenerRegionParameterTest.php
```

### Phase 2: Feature

**Files:**
- `src/Feature/Subscription/SubscriptionFeature.php`

**Specs:**
```yaml
# specs/features/subscription.yaml
name: subscription
group: features
features:
  - name: type-filtering
    specs:
      - acceptanceCriteria: Feature inspects parameter types directly on returned listeners
        criticality: contract
        intent: Filters listeners by inspecting first parameter type without wrapping, keeping implementation simple
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Feature/Subscription/DirectTypeInspectionTest.php

      - acceptanceCriteria: Feature uses SplObjectStorage to cache listener parameter types
        criticality: detail
        intent: Optimizes repeated type lookups by caching reflection results per listener, improving performance
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Feature/Subscription/TypeCacheTest.php

      - acceptanceCriteria: Feature filters listeners by parameter type compatibility
        criticality: contract
        intent: Returns only listeners whose first parameter accepts the event type, enabling type-safe subscriptions
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Feature/Subscription/TypeFilterTest.php

      - acceptanceCriteria: Listeners with object typehint match all events
        criticality: contract
        intent: Enables catch-all listeners that receive every event regardless of specific type
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Feature/Subscription/CatchAllTest.php

      - acceptanceCriteria: Type filtering supports inheritance and interface implementation
        criticality: contract
        intent: Listeners accepting base types receive events of derived types, following Liskov substitution principle
        test: vendor/bin/phpunit tests/PHPUnit/Unit/Feature/Subscription/InheritanceFilteringTest.php

  - name: bound-access-integration
    specs:
      - acceptanceCriteria: Feature integrates NotificationChain with BoundAccess when ExtendedState enabled
        criticality: contract
        intent: Makes NotificationChain accessible in state callbacks via this.notificationChain for internal event emission
        test: vendor/bin/phpunit tests/PHPUnit/Integration/Feature/Subscription/BoundAccessIntegrationTest.php

      - acceptanceCriteria: State callbacks can emit events via this.notificationChain
        criticality: contract
        intent: Enables state entry/exit logic to notify subscribers directly, supporting observer patterns within state machine
        test: vendor/bin/phpunit tests/PHPUnit/Integration/Feature/Subscription/StateCallbackEmitTest.php
```

### Phase 3: Validation

```bash
ddev exec machines/middleware-test-runner/run.sh --spec=specs/chain/notification.yaml
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml
ddev exec machines/middleware-test-runner/run.sh --spec=specs/features/subscription.yaml
ddev atlas
ddev exec composer quality
```

---

## Key Points

### ✅ Global Listener Management

- NotificationChain **owns storage** (global array)
- Storage is **outside Region** (chain-level)
- **subscribe()** provides simple global registration API
- **Deregister function** provides cleanup

### ✅ No Per-Region Storage

- Listeners are **GLOBAL** - hear events from all regions
- Enables **event bubbling** from nested regions
- Supports **cross-region communication**
- Region parameter lets listeners **filter by source**

### ✅ No Middleware Wrapping

- Region::on() **delegates** to chain.subscribe()
- **No link() used** for registration
- SubscriptionFeature **inspects types directly** on returned listeners
- **No wrapLinkToExtractTypes()** - removed complexity

### ✅ Region Context Support

- Listeners receive **Region as second parameter**
- Identifies **event source** (which Region emitted)
- Enables **filtering** by source Region if needed
- Supports **nested region** event bubbling

### ✅ Type Caching

- **SplObjectStorage<callable, string>** caches types
- Avoids **repeated reflection** calls
- Performance optimization
- **NOT for per-Region listener storage**

### ✅ BoundAccess Integration

- Optional **ExtendedState integration**
- State callbacks can **emit events directly**
- Access via **this.notificationChain**

### ✅ Separation of Concerns

- **Storage** (chain): Global array
- **Resolution** (chain provider): Returns all listeners
- **Filtering** (feature): Type compatibility check
- **Execution** (caller): Error isolation, timing, Region context passing

### ✅ Infrastructure Only

Provides mechanism:
- Global listener storage (array)
- Registration API (subscribe)
- Type filtering (feature)
- Region context (second param)

Does NOT provide:
- Event types
- Emission timing
- Execution strategy
- Per-Region filtering

**Applications decide policy.**

---

## Approval Checklist

- [ ] NotificationChain has subscribe($listener) method (no Region parameter)
- [ ] Chain stores listeners globally in simple array
- [ ] Chain provider returns all listeners globally
- [ ] subscribe() returns deregister function
- [ ] Region::on() delegates to subscribe (no link())
- [ ] SubscriptionFeature inspects types directly (no wrapLinkToExtractTypes)
- [ ] Feature uses SplObjectStorage<callable, string> for type caching ONLY
- [ ] Listeners receive Region as optional second parameter to identify source
- [ ] Global listeners enable event bubbling from nested regions
- [ ] BoundAccess integration supports emitting from state callbacks
- [ ] Pattern is clear and extensible

---

## Next Steps

1. Review and approve spec changes
2. Implement core (NotificationChain with subscribe and global array)
3. Implement feature (direct type inspection with caching)
4. Add BoundAccess integration (optional ExtendedState support)
5. Validate (specs, regression, quality)

---

**End of Proposal**
