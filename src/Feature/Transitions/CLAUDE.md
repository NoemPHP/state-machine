# TransitionsFeature - Agent Instructions

## Purpose

Enables **automatic, declarative state transitions** based on guard predicates. Instead of imperatively setting states in action handlers, you define transition rules that are evaluated automatically after every action dispatch. The first guard returning true triggers the transition.

## Key Concepts

### AddTransition BuildStep

The developer-facing API for registering transitions:

```php
use Noem\State\Feature\Transitions\AddTransition;

new AddTransition(
    from: 'idle',           // Source state
    to: 'processing',       // Target state
    guard: fn(object $trigger): bool => $trigger->ready ?? false  // Optional
)
```

**Default Enabled**: TransitionsFeature is active by default in RegionBuilder.

### Guard Predicates

Callables that determine if a transition should fire:

- **Signature**: `fn(object $trigger): bool`
- **Required**: Explicit `: bool` return type
- **Required**: Object parameter (trigger)
- **Optional**: Can type-hint specific event classes
- **Access**: Can use `$this->get()` for extended state (when ExtendedState enabled)

### First-Match-Wins

Only the first guard returning `true` triggers a transition. Evaluation stops immediately.

### LIFO Evaluation Order

Guards are checked in **REVERSE registration order** (Last-In-First-Out). BuildSteps execute in reverse, so:

```php
->addBuildStep(new AddTransition('start', 'default'))   // Checked LAST
->addBuildStep(new AddTransition('start', 'special'))   // Checked FIRST
```

**Pattern**: Register broad/fallback guards first, specific guards last.

### Automatic Evaluation

Transitions are checked automatically after **every** `Region::trigger()` call when:
- State hasn't changed imperatively
- Region is not in final state
- All connected child regions are finished

### Blocking Conditions

Automatic transitions are skipped when:

1. **Imperative state change**: Action handler returned different state
2. **Final state**: `$region->isFinal() === true`
3. **Pending children**: Any connected region is not final

## Usage Patterns

### Basic Transition

```php
$builder->addBuildStep(new AddTransition('idle', 'processing'));
// Equivalent to always-true guard
```

### Conditional Transition

```php
$builder->addBuildStep(new AddTransition(
    'idle',
    'processing',
    fn(object $t): bool => $t->ready ?? false
));
```

### Fallback Pattern

```php
// Register generic fallback FIRST (checked LAST)
$builder
    ->addBuildStep(new AddTransition('start', 'default'))
    // Register specific condition AFTER (checked FIRST)
    ->addBuildStep(new AddTransition('start', 'premium', fn($t): bool => $t->isPremium ?? false));
```

### Conditional Branching

```php
$builder
    ->setStates('start', 'pathA', 'pathB', 'pathC')
    // Register in reverse priority order (LIFO)
    ->addBuildStep(new AddTransition('start', 'pathC', fn($t): bool => $t->priority === 3))
    ->addBuildStep(new AddTransition('start', 'pathB', fn($t): bool => $t->priority === 2))
    ->addBuildStep(new AddTransition('start', 'pathA', fn($t): bool => $t->priority === 1));
```

### Stateful Transitions (Extended State)

```php
$builder
    ->enableFeatures(new ExtendedState())  // Must come before TransitionsFeature
    ->addBuildStep(new AddTransition('processing', 'done', function(object $t): bool {
        return $this->get('progress') >= 100;
    }));
```

### Type-Based Guards

```php
// PSR-14 style event filtering
$builder
    ->addBuildStep(new AddTransition('idle', 'processing', fn(StartEvent $e): bool => true))
    ->addBuildStep(new AddTransition('idle', 'stopped', fn(StopEvent $e): bool => true));

// Generic events silently skip (return false, don't throw)
$region->trigger(new GenericEvent());
```

## Common Pitfalls

### 1. LIFO Evaluation Order Confusion ⚠️

**Problem**: Last-registered transitions are checked first.

```php
// ❌ WRONG - Fallback registered last, matches first
$builder
    ->addBuildStep(new AddTransition('start', 'special', $specificGuard))
    ->addBuildStep(new AddTransition('start', 'default'));  // Always true, checked FIRST!

// ✅ CORRECT - Fallback registered first, checked last
$builder
    ->addBuildStep(new AddTransition('start', 'default'))
    ->addBuildStep(new AddTransition('start', 'special', $specificGuard));
```

**Why**: BuildSteps execute in LIFO order, creating reverse-registration evaluation.

### 2. Missing `: bool` Return Type

**Problem**: RuntimeException thrown.

```php
// ❌ WRONG
fn($t) => true  // Error: "Guards must return bool"

// ✅ CORRECT
fn($t): bool => true
```

### 3. Missing Trigger Parameter

**Problem**: Guard signature doesn't accept trigger.

```php
// ❌ WRONG
fn(): bool => someCondition()

// ✅ CORRECT
fn(object $t): bool => someCondition()  // Accept trigger even if unused
```

### 4. Extended State Not Accessible

**Problem**: ExtendedState feature not enabled or wrong order.

```php
// ❌ WRONG - ExtendedState after TransitionsFeature
$builder->enableFeatures(new TransitionsFeature(), new ExtendedState());

// ✅ CORRECT - ExtendedState before TransitionsFeature
$builder->enableFeatures(new ExtendedState());  // TransitionsFeature auto-enabled
```

**Why**: Feature order matters due to LIFO wrapping. ExtendedState must initialize context schema before transitions try to use it.

### 5. Self-Transitions Don't Fire Events

**Current Behavior**: Transitions from a state to itself are detected and skipped.

```php
new AddTransition('idle', 'idle', $guard);  // onExit/onEnter NOT fired
```

**Why**: Implementation checks `if ($currentState !== $newState)` before calling DoTransition.

**See**: `specs/features/transitions.yaml:281-296` for commented spec on this limitation.

### 6. Imperative State Changes Bypass Transitions

**Expected**: Action returning different state prevents automatic transitions.

```php
$builder->on('idle', MyEvent::class, function(MyEvent $e): string {
    return 'explicit_state';  // Imperative change
});

// AddTransition from 'idle' won't be checked - imperative wins
```

**Why**: Explicit state control takes precedence over declarative rules.

### 7. Parent Transitions Wait for Children

**Behavior**: Parent regions cannot transition until all connected children are final.

```php
$parent->connect($child);

$parent->trigger($event);  // Won't transition if !$child->isFinal()
```

**Why**: Ensures hierarchical state consistency.

### 8. Type Mismatches Silently Skip

**Behavior**: Guards with incompatible trigger types return `false` without throwing.

```php
$builder->addBuildStep(new AddTransition(
    'idle',
    'specific',
    fn(SpecificEvent $e): bool => true
));

$region->trigger(new GenericEvent());  // Guard returns false (type mismatch)
```

**Why**: Enables PSR-14 style event filtering with multiple typed guards.

## Examples

### Simple State Machine

```php
$region = (new RegionBuilder())
    ->setStates('idle', 'processing', 'done')
    ->markInitial('idle')
    ->markFinal('done')
    ->addBuildStep(new AddTransition('idle', 'processing', fn($t): bool => isset($t->start)))
    ->addBuildStep(new AddTransition('processing', 'done'))
    ->build();

$region->trigger((object)['start' => true]);  // idle → processing
$region->trigger((object)[]);                 // processing → done
```

### Multi-Path Workflow

```php
$region = (new RegionBuilder())
    ->setStates('start', 'approved', 'rejected', 'end')
    ->markInitial('start')
    // Register fallback first
    ->addBuildStep(new AddTransition('start', 'rejected'))  // Default: reject
    // Register specific conditions after
    ->addBuildStep(new AddTransition('start', 'approved', fn($t): bool => $t->score >= 80))
    ->addBuildStep(new AddTransition('approved', 'end'))
    ->addBuildStep(new AddTransition('rejected', 'end'))
    ->build();

$region->trigger((object)['score' => 90]);  // start → approved → end
```

### Hierarchical State Machine

```php
$childBuilder = new RegionBuilder();

$child = $childBuilder
    ->setStates('child_working', 'child_done')
    ->markFinal('child_done')
    ->addBuildStep(new AddTransition('child_working', 'child_done'))
    ->build();

$parent = $childBuilder
    ->newInstance()
    ->setStates('parent_start', 'parent_end')
    ->connect($child)
    ->addBuildStep(new AddTransition('parent_start', 'parent_end'))
    ->build();

// Parent cannot transition until child finishes
$parent->trigger((object)[]);  // parent_start (child not finished)
$child->trigger((object)[]);   // child_working → child_done
$parent->trigger((object)[]);  // parent_start → parent_end (child finished)
```

## Integration Points

### ChainMail Services

TransitionsFeature registers three services:

```php
// In TransitionsFeature::__invoke()
$chainMail->supply(
    fn(ConnectedRegions $c, Events $e): DoTransition => new DoTransition($c, $e),
    fn(InvokeCallback $i, PrepareInvokable $p): Guard => new Guard($i, $p),
    fn(): TransitionRegistry => new TransitionRegistry(),
);
```

- **DoTransition** - Executes state transitions with lifecycle events
- **Guard** - Validates and executes guard predicates
- **TransitionRegistry** - Stores transitions per region (SplObjectStorage)

### DispatchAction Chain Hook

TransitionsFeature links into DispatchAction to check transitions after every action:

```php
$dispatchAction->link(function (Action $action, callable $next) {
    $currentState = $action->currentState;
    $newState = $next($action);  // Execute action

    // Skip if imperative change
    if ($currentState !== $newState) return $newState;

    // Skip if final state
    if ($action->region->isFinal()) return $currentState;

    // Skip if children not finished
    if (array_any($connections, fn($r) => !$r->isFinal())) return $currentState;

    // Evaluate guards and return target if match
    // ...
});
```

### Guard Chain

Integrates with **PrepareInvokable** (for `$this` binding) and **InvokeCallback** (for execution):

1. Type compatibility check (returns `false` for mismatches)
2. Return type validation (throws if not `: bool`)
3. Callback preparation (binds extended state context)
4. Guard execution

### DoTransition Chain

Executes state change with lifecycle events:

1. `onExitState(previousState)` - Cleanup in old state
2. `onEnterParent(payload)` - Notify connected children
3. `onEnterState(currentState)` - Initialize in new state

### ConnectedRegions

Parent regions query connected children before transitioning:

```php
$connections = $connectedRegions->call(new Connection($action->region));
if (array_any($connections, fn($region) => !$region->isFinal())) {
    return $action->currentState;  // Wait for children
}
```

### ExtendedState Feature

**Order Dependency**: ExtendedState **must** be enabled before TransitionsFeature.

```php
// ✅ CORRECT
->enableFeatures(new ExtendedState())  // Creates context schema
// TransitionsFeature auto-enabled or explicit

// Guards can now access extended state
->addBuildStep(new AddTransition('idle', 'done', function(object $t): bool {
    return $this->get('count') >= 10;
}))
```

### RegionLoader (YAML)

Loader converts YAML transition definitions to `AddTransition` BuildSteps:

```yaml
states:
  idle:
    transitions:
      - target: processing
        guard: 'isset($trigger->start)'
      - target: cancelled
        guard: '$trigger->cancel ?? false'
```

**Generated**:
```php
$builder
    ->addBuildStep(new AddTransition('idle', 'processing', eval('...')))
    ->addBuildStep(new AddTransition('idle', 'cancelled', eval('...')));
```

### AsyncFeature

Transitions work seamlessly with async operations. Guards and DoTransition callbacks are scheduled by the coroutine scheduler.

## Related Files

- `src/Feature/Transitions/TransitionsFeature.php` - Feature implementation
- `src/Feature/Transitions/AddTransition.php` - BuildStep API
- `src/Feature/Transitions/TransitionRegistry.php` - Per-region storage
- `src/Feature/Transitions/Chains/Guard.php` - Guard validation/execution chain
- `src/Chains/DoTransition.php` - Transition execution with lifecycle events
- `specs/features/transitions.yaml` - Complete acceptance criteria
- `tests/PHPUnit/Integration/Feature/Transitions/` - Integration tests
