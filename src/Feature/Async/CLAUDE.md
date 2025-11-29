# AsyncFeature - Coroutine-Based Asynchronous Operations

## Purpose

AsyncFeature provides **cooperative multitasking** using PHP generators (coroutines) for state machine callbacks. It enables non-blocking I/O operations, multi-step async actions, lazy-evaluated resolvers, and concurrent task execution—all within synchronous PHP code.

**Key Value**: Allows callbacks to pause and resume execution across multiple triggers, enabling responsive state machines that don't block on I/O or expensive computations.

## Public API

### Async Callbacks

Any callback can become async by returning a Generator:

```php
->onAction('state', function(object $trigger) {
    // Step 1
    $this->set('status', 'started');
    yield;  // Pause - resumes on next trigger

    // Step 2
    $this->set('status', 'processing');
    yield;  // Pause again

    // Step 3
    $this->set('status', 'complete');
})
```

**Behavior**: Each `yield` pauses the callback. The scheduler resumes it on the next `trigger()` call.

### Call API - Async Operations

#### Call::call() - Execute Sub-Generators

```php
use Noem\State\Feature\Async\Call;

->onAction('state', function(object $trigger) {
    $buffer = [];
    $result = yield Call::call(new Load('file.txt'), $buffer);
    // $buffer contains all yielded characters
    // $result is the generator's return value
})
```

**CRITICAL**: Pass `$buffer` by reference to collect yielded values. The return value is metadata, not content.

#### Call::waitForSecs() - Delay Execution

```php
->onAction('state', function(object $trigger) {
    $this->set('message', 'Starting...');
    yield Call::waitForSecs(5);  // Wait 5 seconds
    $this->set('message', 'Done!');
})
```

#### Call::fork() - Independent Task

```php
->onAction('state', function(object $trigger) {
    $forkedTask = yield Call::fork(function() {
        yield;
        // This runs independently
    });
    // Continue without waiting for forked task
})
```

#### Call::cancel() - Cancel Task

```php
$task = yield Call::fork($backgroundWork);
// Later...
yield Call::cancel($task);
```

### Resolvers - Lazy Computed Properties

Resolvers are lazily-evaluated properties that compute on first access and cache results:

#### YAML Configuration

```yaml
context:
  resolvers:
    - name: userData
      run: !php |
        return function() {
          // Expensive operation
          yield;
          $data = $this->get('userId');
          yield;
          return fetchUserFromDatabase($data);
        };
```

#### Programmatic Configuration

```php
use Noem\State\Feature\Async\AddResolver;

$builder->addBuildStep(new AddResolver(
    'userData',
    function() {
        yield;
        $userId = $this->get('userId');
        yield;
        return fetchUserFromDatabase($userId);
    }
));
```

#### Access in Callbacks

```php
->onEnter('profile', function(object $trigger) {
    // First access triggers computation
    $user = $this->get('userData');
    // Subsequent access returns cached value
    $sameUser = $this->get('userData');
})
```

**Key Behavior**:
- Computes ONLY when accessed
- Caches result automatically
- Invalidates cache when dependencies change
- Dependencies tracked via Mesh access during resolution

### I/O Operations

#### Load - File Reading

```php
use Noem\State\Feature\Async\IO\Load;

->onAction('state', function(object $trigger) {
    $buffer = [];
    $metadata = yield Call::call(new Load('/path/file.txt'), $buffer);
    $content = implode('', $buffer);
})
```

#### Fetch - HTTP Requests

```php
use Noem\State\Feature\Async\IO\Fetch;

->onAction('state', function(object $trigger) {
    $buffer = [];
    $metadata = yield Call::call(
        new Fetch('https://api.example.com/data', 'GET'),
        $buffer
    );
    $response = implode('', $buffer);
})
```

**Options**:
```php
new Fetch(
    url: 'https://example.com',
    method: 'POST',
    headers: ['Content-Type' => 'application/json'],
    body: json_encode(['key' => 'value'])
)
```

#### Exec - Shell Commands

```php
use Noem\State\Feature\Async\IO\Exec;

->onAction('state', function(object $trigger) {
    $buffer = [];
    $exitCode = yield Call::call(new Exec('ls -la'), $buffer);
    $output = implode('', $buffer);
})
```

## Usage Patterns

### Multi-Step Async Action

```php
$region = (new RegionBuilder())
    ->enableFeatures(new AsyncFeature())
    ->setStates('idle', 'processing')
    ->onAction('processing', function(object $trigger) {
        $this->set('progress', 0);
        yield;

        $this->set('progress', 33);
        yield;

        $this->set('progress', 66);
        yield;

        $this->set('progress', 100);
    })
    ->build();

// Each trigger advances one step
$region->trigger($event);  // progress = 0
$region->trigger($event);  // progress = 33
$region->trigger($event);  // progress = 66
$region->trigger($event);  // progress = 100
```

### Concurrent Actions

```php
$region = (new RegionBuilder())
    ->enableFeatures(new AsyncFeature())
    ->setStates('active')
    ->onAction('active', function(object $trigger) use (&$log) {
        $log[] = 'task1-start';
        yield;
        $log[] = 'task1-end';
    })
    ->onAction('active', function(object $trigger) use (&$log) {
        $log[] = 'task2-start';
        yield;
        $log[] = 'task2-end';
    })
    ->build();

$region->trigger($event);
// $log = ['task1-start', 'task2-start']

$region->trigger($event);
// $log = ['task1-start', 'task2-start', 'task1-end', 'task2-end']
```

**Behavior**: All tasks advance one step per trigger (cooperative multitasking).

### Lazy Resolver with Dependencies

```yaml
context:
  userId: 123
  resolvers:
    - name: userProfile
      run: !php |
        return function() {
          $id = $this->get('userId');  # Tracked as dependency
          yield;
          return fetchUser($id);
        };
```

```php
->onEnter('step1', function(object $trigger) {
    $this->set('userId', 123);
    $profile = $this->get('userProfile');  // Computes with userId=123
})
->onEnter('step2', function(object $trigger) {
    $this->set('userId', 456);  // Cache invalidates!
    $profile = $this->get('userProfile');  // Recomputes with userId=456
})
```

### Non-Blocking File I/O

```php
use Noem\State\Feature\Async\Call;
use Noem\State\Feature\Async\IO\Load;

->onAction('processing', function(object $trigger) {
    $buffer = [];
    yield Call::call(new Load('large-file.txt'), $buffer);

    $content = implode('', $buffer);
    $this->set('fileData', $content);
})
```

**Why Non-Blocking**: Each character read yields control, allowing other tasks to progress.

## Integration Points

### With ExtendedState (MANDATORY)

**CRITICAL**: AsyncFeature MUST be loaded AFTER ExtendedState:

```php
// ✅ CORRECT
->enableFeatures(
    new ExtendedState(),   // First
    new AsyncFeature()     // Second
)

// ❌ FAILS with "Unexpected item 'context › resolvers'"
->enableFeatures(
    new AsyncFeature(),
    new ExtendedState()
)
```

**Why**: AsyncFeature extends the `context:` schema created by ExtendedState. Loading AsyncFeature first means it tries to extend a non-existent schema.

**Reference**: See src/Feature/Async/AsyncFeature.php:178-203 (extendBuilderSchema method).

### With RegionLoader

AsyncFeature hooks into `LoaderChains\Schema` to add `resolvers` to the YAML schema:

```yaml
# Enabled by AsyncFeature
context:
  resolvers:
    - name: myResolver
      run: !php "return function() { yield; return 'value'; };"
```

### With InvokeCallback Chain

AsyncFeature intercepts ALL callback invocations to:
1. Detect Generator return values
2. Enqueue generators as tasks
3. Track callback → task mapping
4. Return last yielded value

See src/Feature/Async/AsyncFeature.php:103-164.

### With DispatchAction Chain

AsyncFeature hooks into action dispatch to defer scheduler ticking:

```php
// In deferTicksUntilActionComplete (line 66-101)
1. Clean up finished tasks
2. Process all action callbacks
3. Tick scheduler ONCE (all tasks advance one step)
```

**Why**: Ensures fairness—every task progresses exactly once per trigger.

## Architecture

### Component Overview

```
AsyncFeature
    ├── CoroutineScheduler (per-region task execution)
    │   ├── Task queue (SplObjectStorage)
    │   ├── Generator → Task mapping (WeakMap)
    │   ├── Last yielded values (WeakMap)
    │   ├── Completion callbacks
    │   └── Pause/resume state
    │
    ├── Task (wraps Generator)
    │   ├── run() - advance one step
    │   ├── setSendValue() - send value to generator
    │   ├── getReturn() - get final return value
    │   └── isFinished() - check completion
    │
    ├── Call (async operation commands)
    │   ├── call() - execute sub-generator
    │   ├── waitForSecs() - delay
    │   ├── fork() - independent task
    │   └── cancel() - cancel task
    │
    ├── Ornament (lazy resolver)
    │   ├── Dependency tracking (Mesh access)
    │   ├── Cache management
    │   ├── Invalidation on dependency change
    │   └── Middleware for Mesh operations
    │
    └── I/O Operations
        ├── StreamHandler - character-by-character streaming
        ├── Load - file reading
        ├── Fetch - HTTP requests
        └── Exec - shell commands
```

### Callback → Task Lifecycle

```
1. Callback invoked → returns Generator
2. Check callbackTaskMap (WeakMap<callable, Task>)
   ├─ Task exists AND finished → remove from map, create new
   ├─ Task exists AND running → return last yielded value
   └─ No task → create new task, store in map
3. Enqueue task in scheduler
4. Scheduler ticks (deferred until all callbacks processed)
5. Task advances one step
6. Yielded value stored in lastResults (WeakMap)
7. On next trigger, repeat from step 2
```

**Key Data Structures**:
- `callbackTaskMap: WeakMap<callable, Task>` - tracks which callback owns which task
- `coroutinesByRegion: SplObjectStorage<Region, CoroutineScheduler>` - one scheduler per region
- `lastResults: WeakMap<Task, mixed>` - stores last yielded value per task

### Deferred Ticking Mechanism

```php
// Traditional approach (would cause unfairness):
callback1 → enqueue → tick  // Callback 1 advances
callback2 → enqueue → tick  // Callback 2 advances (but callback 1 advanced again!)

// AsyncFeature approach (fair):
callback1 → enqueue
callback2 → enqueue
tick (ONCE)  // Both advance exactly once
```

**Implementation** (AsyncFeature.php:73-100):
1. Hook into `DispatchAction` chain
2. Before callbacks: clean up finished tasks
3. Execute all callbacks (enqueue any generators)
4. After callbacks: tick scheduler ONCE

### Ornament Resolver Pattern

Ornaments extend Mesh with lazy resolution:

```php
// On first access:
$value = $this->get('resolverName');
    ↓
Ornament::trackOffsetGet()
    ↓
startResolution()
    ↓
Call resolver with OrnamentResolver
    ↓
Track dependencies during execution
    ↓
Cache result
    ↓
Return cached value

// On dependency change:
$this->set('dependency', newValue);
    ↓
Ornament::trackOffsetSet()
    ↓
Dependency changed? → reset()
    ↓
Cache invalidated, will recompute on next access
```

**Dependency Tracking** (Ornament.php:98-105):
- During resolution, `isResolving = true`
- Any Mesh access via `OrnamentResolver::get()` records dependency
- When tracked dependency changes → cache invalidates

## Critical Idiosyncrasies

### 1. Feature Loading Order (MANDATORY)

**CRITICAL**: AsyncFeature MUST load AFTER ExtendedState.

```php
// ✅ CORRECT
new ExtendedState(), new AsyncFeature()

// ❌ WRONG - Runtime error
new AsyncFeature(), new ExtendedState()
```

**Error**: `"Unexpected item 'context › resolvers'"`

**Why**: AsyncFeature extends ExtendedState's `context` schema. If ExtendedState hasn't created the schema yet, extension fails.

### 2. Call::call Buffer Pattern

**CRITICAL**: `Call::call()` return value is metadata, NOT content.

```php
// ❌ WRONG - $content will be metadata/null
$content = yield Call::call(new Load('file.txt'));

// ✅ CORRECT - $buffer accumulates content
$buffer = [];
$metadata = yield Call::call(new Load('file.txt'), $buffer);
$content = implode('', $buffer);
```

**Why**: StreamHandler yields characters but returns `wrapper_data` metadata. The buffer is the intended API for I/O results.

**Reference**: core-development skill lines 478-492.

### 3. Deferred Tick Timing

Tasks advance AFTER all callbacks process, not during:

```php
->onAction('state', function() {
    yield;  // Pauses, but doesn't tick yet
})
->onAction('state', function() {
    yield;  // Also pauses, doesn't tick yet
})
// NOW scheduler ticks once, advancing both tasks
```

**Implication**: All concurrent tasks progress exactly once per trigger.

### 4. Finished Task Cleanup Timing

**CRITICAL**: Finished tasks are cleaned up at the START of action dispatch, not end.

```php
// In deferTicksUntilActionComplete (lines 79-91)
// BEFORE processing callbacks:
foreach ($this->callbackTaskMap as $callback => $task) {
    if ($task->isFinished()) {
        unset($this->callbackTaskMap[$callback]);
    }
}
```

**Why**: Prevents race condition where:
1. Old task finishes during tick
2. Callback creates new task before cleanup
3. Cleanup accidentally removes new task's mapping

### 5. WeakMap for Callback Tracking

`callbackTaskMap` uses `WeakMap<callable, Task>`:

```php
private \WeakMap $callbackTaskMap;

// Usage
$this->callbackTaskMap[$callback->handler] = $task;
```

**Implications**:
- Callback identity is key (object instance)
- If callback is GC'd, task mapping auto-removed
- Same callback = same task (sequential executions reuse if finished)

### 6. Task Persistence vs Replacement

When callback executes:

```php
// If task exists AND finished:
if ($this->callbackTaskMap->offsetExists($handler)) {
    $task = $this->callbackTaskMap[$handler];
    if ($task->isFinished()) {
        unset($this->callbackTaskMap[$handler]);
        // Fall through to create NEW task
    } else {
        // Return last yielded, task continues
        return $lastYielded;
    }
}
```

**Behavior**:
- Running task → persist across triggers
- Finished task → remove and create new on next invocation
- Allows callback to run multiple times sequentially

### 7. Scheduler Reentrancy Protection

```php
// In CoroutineScheduler::tick() (line 84-86)
if ($this->isBusy) {
    return;  // Prevent nested ticks
}
$this->isBusy = true;
```

**Why**: Prevents infinite recursion if a task tries to trigger events that would tick the scheduler again.

### 8. Ornament Dependency Tracking Scope

Ornament only tracks dependencies accessed DURING resolution:

```php
// Resolver
function() {
    $dep1 = $this->get('foo');  // ✅ Tracked
    yield;
    $dep2 = $this->get('bar');  // ✅ Tracked
    return computeValue($dep1, $dep2);
}

// Outside resolver
function() {
    $this->set('baz', 'value');  // ❌ NOT tracked
}
```

**Implication**: Only dependencies actually accessed during resolver execution trigger cache invalidation.

### 9. One Scheduler Per Region

```php
// In enqueueCoroutines (line 118-120)
if (!$this->coroutinesByRegion->contains($context->region)) {
    $this->coroutinesByRegion->attach($region, new CoroutineScheduler());
}
```

**Behavior**:
- Each region has isolated scheduler
- Parent and child regions have separate task queues
- Tasks don't propagate between regions

### 10. Call Objects vs Regular Yields

Scheduler handles `Call` objects specially:

```php
// In CoroutineScheduler::tick() (line 100-105)
$yielded = $task->run();
if ($yielded instanceof Call) {
    $yielded($task, $this);  // Execute Call
} else {
    $this->lastResults[$task] = $yielded;  // Store regular yield
}
```

**Behavior**:
- Regular `yield` → stores value, task pauses
- `yield Call::*()` → executes operation, may pause/resume/fork

## Common Patterns

### State Machine Event Loop

```php
$region = (new RegionBuilder())
    ->enableFeatures(new AsyncFeature())
    ->setStates('running')
    ->onEnter('running', function(object $trigger) {
        while (true) {
            $event = yield $this->waitForEvent();
            $this->dispatch($event);
        }
    })
    ->build();

// External driver
while ($region->currentState() === 'running') {
    $region->trigger(new \stdClass());
    usleep(10000);  // 10ms tick rate
}
```

### Retry Pattern

```php
->onAction('fetching', function(object $trigger) {
    $maxRetries = 3;
    $attempt = 0;

    while ($attempt < $maxRetries) {
        $buffer = [];
        try {
            yield Call::call(new Fetch($url), $buffer);
            $this->set('data', implode('', $buffer));
            return;
        } catch (\Exception $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            yield Call::waitForSecs(2);  // Wait before retry
        }
    }
})
```

### Computed Property with Fallback

```yaml
context:
  useCache: true
  resolvers:
    - name: userData
      run: !php |
        return function() {
          if ($this->get('useCache')) {
            $cached = $this->get('cachedUser', null);
            if ($cached !== null) {
              return $cached;
            }
          }
          yield;
          $data = fetchFromApi();
          $this->set('cachedUser', $data);
          return $data;
        };
```

### Parallel I/O Operations

```php
->onAction('loading', function(object $trigger) {
    // Fork independent tasks
    $task1 = yield Call::fork(function() {
        $buffer = [];
        yield Call::call(new Load('file1.txt'), $buffer);
        return implode('', $buffer);
    });

    $task2 = yield Call::fork(function() {
        $buffer = [];
        yield Call::call(new Fetch('https://api.example.com'), $buffer);
        return implode('', $buffer);
    });

    // Both execute concurrently
    // Main task continues without waiting
})
```

## Testing Patterns

### Asserting Task Completion

```php
public function testTaskCompletesAfterThreeYields(): void
{
    $builder = new RegionBuilder();
    $builder->enableFeatures(new AsyncFeature());

    $completed = false;

    $region = $builder
        ->setStates('active')
        ->onAction('active', function() use (&$completed) {
            yield;
            yield;
            yield;
            $completed = true;
        })
        ->build();

    $this->assertFalse($completed);

    $region->trigger($event);
    $this->assertFalse($completed);

    $region->trigger($event);
    $this->assertFalse($completed);

    $region->trigger($event);
    $this->assertFalse($completed);

    $region->trigger($event);
    $this->assertTrue($completed);  // Completed after 4th trigger
}
```

### Testing Resolver Laziness

```php
public function testResolverOnlyExecutesWhenAccessed(): void
{
    $executed = false;

    $region = $builder
        ->build([
            'loader' => [
                'array' => [
                    'context' => [
                        'resolvers' => [
                            [
                                'name' => 'lazy',
                                'run' => function() use (&$executed) {
                                    $executed = true;
                                    yield;
                                    return 'value';
                                }
                            ]
                        ]
                    ]
                ]
            ]
        ]);

    // Trigger multiple times without accessing
    $region->trigger($event);
    $region->trigger($event);

    $this->assertFalse($executed, 'Should not execute until accessed');

    // Access in callback
    $region->onAction('state', fn() => $this->get('lazy'));
    $region->trigger($event);
    // Give resolver time to execute
    $region->trigger($event);

    $this->assertTrue($executed);
}
```

### Mocking I/O Operations

```php
// Create test file
$tempFile = tempnam(sys_get_temp_dir(), 'test');
file_put_contents($tempFile, 'test content');

->onAction('state', function() use ($tempFile) {
    $buffer = [];
    yield Call::call(new Load($tempFile), $buffer);
    $content = implode('', $buffer);
    $this->set('loaded', $content);
})

// Clean up
unlink($tempFile);
```

## When to Use AsyncFeature

### ✅ Use When

- Callbacks need to execute over multiple triggers
- Performing I/O operations (file, network, database)
- Implementing event loops or polling mechanisms
- Long-running computations that should be interruptible
- Lazy evaluation of expensive properties
- Multiple independent tasks running concurrently
- Implementing timeouts or delays

### ❌ Avoid When

- All operations are synchronous
- Callbacks complete in single execution
- No I/O or expensive computations
- Event-driven async (use actual event system instead)
- Parallelism needed (PHP coroutines are cooperative, not parallel)

## Relationship to Other Features

| Feature | Relationship | Notes |
|---------|--------------|-------|
| **ExtendedState** | Depends on | MUST load ExtendedState FIRST |
| **RegionLoader** | Extends schema | Adds `resolvers` to YAML `context:` |
| **TransitionsFeature** | Independent | Guards can use async callbacks |
| **TemplateFeature** | Works with | Templates can use async resolvers |
| **AiFeature** | Works with | AI prompts can be async resolvers |
| **Holon** | Integrated | Holon machines support async operations |

## Performance Considerations

### Task Overhead

Each async callback creates:
- 1 Task object
- 1 entry in callbackTaskMap (WeakMap)
- 1 entry in scheduler queue (SplObjectStorage)
- 1 entry in lastResults (WeakMap)

**Recommendation**: Use async only when necessary. Synchronous callbacks are faster.

### Memory Management

WeakMap usage means:
- Tasks are GC'd when callback is GC'd
- No manual cleanup needed for most cases
- Long-running tasks hold memory until completion

### Scheduler Tick Cost

Each tick iterates ALL tasks in queue:

```php
// In CoroutineScheduler::tick()
foreach ($this->queue as $task) {
    if (!$paused) {
        $task->run();  // O(n) where n = task count
    }
}
```

**Implication**: 100 concurrent tasks = 100 iterations per trigger. Keep task count reasonable.

## Files Reference

| File | Purpose |
|------|---------|
| `src/Feature/Async/AsyncFeature.php` | Main feature implementation |
| `src/Feature/Async/CoroutineScheduler.php` | Task scheduler with pause/resume |
| `src/Feature/Async/Task.php` | Generator wrapper |
| `src/Feature/Async/Call.php` | Async operation commands |
| `src/Feature/Async/Ornament.php` | Lazy resolver with dependency tracking |
| `src/Feature/Async/OrnamentResolver.php` | Resolver execution context |
| `src/Feature/Async/AddResolver.php` | BuildStep for adding resolvers |
| `src/Feature/Async/Config/AsyncConfig.php` | Config accessor for async settings |
| `src/Feature/Async/IO/StreamHandler.php` | Character-by-character streaming |
| `src/Feature/Async/IO/Load.php` | File reading operation |
| `src/Feature/Async/IO/Fetch.php` | HTTP request operation |
| `src/Feature/Async/IO/Exec.php` | Shell command execution |

## Summary Checklist

When working with AsyncFeature:

- [ ] Load AsyncFeature AFTER ExtendedState
- [ ] Use `yield` to pause, not `return` (unless final)
- [ ] `Call::call()` requires buffer parameter for I/O
- [ ] One scheduler per region (isolated task queues)
- [ ] Deferred tick = all tasks advance once per trigger
- [ ] WeakMap tracks callback → task mapping
- [ ] Finished tasks cleaned up at START of dispatch
- [ ] Resolvers compute lazily on first access
- [ ] Ornament tracks dependencies during resolution
- [ ] Cache invalidates when tracked dependencies change
- [ ] `Call` objects execute operations, regular yields store values
- [ ] Scheduler prevents reentrancy (isBusy flag)
- [ ] I/O operations yield characters, return metadata

---

**Last Updated**: 2025-11-28
**Feature Status**: Stable, production-ready
**Spec Coverage**: Comprehensive integration and unit test coverage
**Known Limitations**: Cooperative multitasking only (not parallel), PHP generator overhead
