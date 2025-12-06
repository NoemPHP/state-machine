# Subscription Feature - Component-Specific Guide

## 🎯 Feature Purpose

The Subscription feature provides a global event notification system with type-based filtering for Region state machines.

**Key Capabilities:**
- Global listener registration across all regions
- Automatic type filtering based on event classes
- Efficient type caching using SplObjectStorage
- Region source identification via optional second parameter

## 🏗️ Architecture Overview

### Core Components

```
SubscriptionFeature (Feature)
    └─> Hooks NotificationChain
        └─> Filters listeners by event type
        └─> Uses SplObjectStorage for type caching

NotificationChain (Chain)
    └─> Global listener storage (array)
    └─> subscribe() returns deregister function
    └─> Provider returns all listeners

Notify (Context Parameter)
    └─> region: Region (event source)
    └─> event: object (event payload)

Region::on(callable $listener)
    └─> Delegates to NotificationChain::subscribe()
    └─> Returns deregister function
```

## 🔑 Critical Design Decisions

### 1. Global Listener Storage (NOT Per-Region)

**IMPORTANT:** Listeners are stored GLOBALLY, not per-Region.

```php
// ✅ CORRECT - Global storage
class NotificationChain extends Chain {
    private array $listeners = [];  // Global across ALL regions
}

// ❌ WRONG - Per-Region storage (breaks event bubbling)
class NotificationChain extends Chain {
    private \SplObjectStorage $listenersByRegion; // NO!
}
```

**Why Global?**
- Enables event bubbling from nested regions
- Parent regions can listen to child events
- Listeners can filter by Region parameter if needed

### 2. Region as Second Parameter

**Listeners receive Region as optional second parameter:**

```php
// Signature: callable(object $event, ?Region $region = null)

$region->on(function (MyEvent $event, ?Region $source = null) {
    // $source tells you which region emitted the event
});
```

**Why Optional?**
- Backwards compatibility with simple listeners
- Not all listeners need source identification
- Allows catch-all listeners: `fn(object $e) => ...`

### 3. SplObjectStorage for Type Caching ONLY

**CRITICAL:** SplObjectStorage is for TYPE CACHING, not listener storage.

```php
// ✅ CORRECT - Type cache
private \SplObjectStorage $typeCache;  // Maps callable -> string (type name)

$this->typeCache[$listener] = 'MyEvent';  // Cache the type

// ❌ WRONG - Listener storage
private \SplObjectStorage $listeners;  // Don't use for listener storage!
```

**Why Cache Types?**
- Reflection is expensive
- Type inspection called on every event emission
- Cache makes repeated emissions O(1) instead of O(n) reflection calls

### 4. Type Filtering Happens in Middleware

**The feature hooks the NotificationChain to filter:**

```php
// Feature adds middleware that:
// 1. Gets all listeners from provider (global list)
// 2. Filters by type compatibility
// 3. Returns only matching listeners

$notificationChain->link(function (Notify $context, callable $next) {
    $allListeners = $next($context);  // Get global list

    return array_filter($allListeners, function ($listener) use ($context) {
        $expectedType = $this->getListenerType($listener);
        return $expectedType === 'object' || $context->event instanceof $expectedType;
    });
}, prepend: true);  // Run FIRST to filter before returning
```

## 📐 Integration with ChainMail

### Feature Invocation Pattern

```php
public function __invoke(ChainMail $chainMail): void
{
    // Use chainMail->use() to register middleware hooks
    $chainMail->use($this->addTypeFiltering(...));
}

private function addTypeFiltering(Notification $notificationChain): void
{
    // ChainMail automatically resolves Notification from container
    // Do NOT add callable $next parameter - that breaks dependency injection

    $notificationChain->link(/* ... */);
}
```

**CRITICAL MISTAKE TO AVOID:**

```php
// ❌ WRONG - This breaks ChainMail dependency injection
private function addTypeFiltering(
    Notification $notificationChain,
    callable $next  // ERROR: ChainMail can't resolve 'callable'
): Notification {
    // ...
    return $next($notificationChain);  // ERROR: No $next parameter!
}

// ✅ CORRECT - ChainMail can resolve all parameters
private function addTypeFiltering(
    Notification $notificationChain
): void {
    // Just hook the chain directly
    $notificationChain->link(/* ... */);
}
```

## 🧪 Testing Guidelines

### Test Organization

```
tests/PHPUnit/Unit/Chains/Notification/
    ├─ GlobalListenerStorageTest.php    # Tests global storage (not per-Region)
    ├─ SubscribeTest.php                # Tests subscribe() method
    ├─ DeregisterFunctionTest.php       # Tests returned deregister closure
    └─ ...

tests/PHPUnit/Unit/Core/Region/
    ├─ ListenerRegionParameterTest.php  # Tests Region as second param
    └─ ...

tests/PHPUnit/Unit/Feature/Subscription/
    ├─ DirectTypeInspectionTest.php     # Tests reflection-based type checking
    ├─ TypeCacheTest.php                # Tests SplObjectStorage caching
    ├─ TypeFilterTest.php               # Tests type-based filtering
    ├─ CatchAllTest.php                 # Tests object typehint catch-all
    └─ InheritanceFilteringTest.php     # Tests inheritance/interface matching
```

### Test Patterns

**Testing Global Listeners:**

```php
public function testListenersAreGlobalAcrossRegions(): void
{
    $builder = new RegionBuilder();
    $region1 = $builder->build();
    $region2 = $builder->newInstance()->build();  // Shares ChainMail

    $callCount = 0;
    $region1->on(fn($e) => $callCount++);  // Register on region1

    // Emit from region2
    $listeners = $region2->notificationChain->call(new Notify($region2, $event));
    foreach ($listeners as $listener) {
        $listener($event, $region2);
    }

    $this->assertEquals(1, $callCount);  // Listener heard it!
}
```

**Testing Type Filtering:**

```php
public function testOnlyMatchingTypeReceivesEvent(): void
{
    $region = (new RegionBuilder())
        ->enableFeatures(new SubscriptionFeature())
        ->build();

    $region->on(fn(SpecificEvent $e) => $specificCalled = true);
    $region->on(fn(OtherEvent $e) => $otherCalled = true);

    $listeners = $region->notificationChain->call(
        new Notify($region, new SpecificEvent())
    );

    $this->assertCount(1, $listeners);  // Only SpecificEvent listener
}
```

## 🚨 Common Pitfalls

### 1. Don't Try to Access Region Inside Provider

```php
// ❌ WRONG - Provider doesn't have access to specific Region
parent::__construct(
    provider: function (Notify $context): array {
        return $this->listeners[$context->region];  // ERROR: No per-Region storage!
    }
);

// ✅ CORRECT - Return all listeners globally
parent::__construct(
    provider: function (Notify $context): array {
        return $this->listeners;  // Global list
    }
);
```

### 2. Don't Add $next Parameter to ChainMail->use() Callbacks

```php
// ❌ WRONG - ChainMail can't resolve 'callable'
$chainMail->use(function(Notification $chain, callable $next) {
    $chain->link(/* ... */);
    return $next($chain);  // Service 'callable' not found!
});

// ✅ CORRECT - Only resolvable services
$chainMail->use(function(Notification $chain) {
    $chain->link(/* ... */);
});
```

### 3. Don't Forget prepend: true for Filtering Middleware

```php
// ❌ WRONG - Filter runs AFTER provider, doesn't affect return
$notificationChain->link(function(Notify $ctx, callable $next) {
    $listeners = $next($ctx);
    return array_filter($listeners, /* ... */);
});  // Missing prepend!

// ✅ CORRECT - Filter runs FIRST
$notificationChain->link(function(Notify $ctx, callable $next) {
    $listeners = $next($ctx);
    return array_filter($listeners, /* ... */);
}, prepend: true);  // Run before provider
```

## 📊 Type Filtering Logic

### Type Resolution

```php
// Extract type from listener's first parameter
$reflection = new \ReflectionFunction($listener);
$params = $reflection->getParameters();
$type = $params[0]->getType();

// Handle different type scenarios:
// - object         → Catch-all (matches everything)
// - SpecificClass  → Only instances of that class
// - ParentClass    → Matches parent and derived classes
// - Interface      → Matches all implementations
```

### Instance Checking

```php
// Check if event matches listener's expected type
if ($expectedType === 'object') {
    return true;  // Catch-all listeners receive everything
}

return $context->event instanceof $expectedType;  // instanceof handles inheritance
```

## 🔗 Feature Dependencies

**This feature is INDEPENDENT - no dependencies on other features.**

- Does NOT require ExtendedState
- Does NOT require AsyncFeature
- Does NOT require TransitionsFeature
- Can be enabled standalone

**Works with:**
- **BoundAccess (future)**: Will integrate to emit events from state callbacks
- **Any feature**: All features can use the notification system

## 📝 Specification Files

- `specs/chain/notification.yaml` - NotificationChain behavior (7 specs)
- `specs/features/subscription.yaml` - SubscriptionFeature behavior (7 specs)
- `specs/core/region.yaml` - Region::on() method (3 specs in notification-subscription feature)

## 🎓 Key Takeaways

1. **Listeners are GLOBAL** - stored in simple array, not per-Region
2. **Region is second parameter** - listeners can identify event source
3. **SplObjectStorage = type cache** - not for listener storage
4. **Type filtering via middleware** - hooks NotificationChain to filter
5. **ChainMail dependency injection** - only resolvable services as parameters
6. **No feature dependencies** - fully standalone feature

## 🤝 Integration with Region

```php
class Region {
    public readonly Chains\Notification $notificationChain;

    public function on(callable $listener): callable {
        return $this->notificationChain->subscribe($listener);
    }
}

// Usage:
$deregister = $region->on(function(MyEvent $e, ?Region $source = null) {
    // Handle event
});

// Later:
$deregister();  // Remove listener
```

---

**When modifying this feature:**
- Always maintain global listener storage
- Preserve Region parameter signature
- Keep type caching for performance
- Ensure middleware uses prepend: true
- Test with multiple regions sharing ChainMail
