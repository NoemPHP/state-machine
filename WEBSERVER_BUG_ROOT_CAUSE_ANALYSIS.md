# Webserver Bug: Root Cause Analysis

## Problem Statement
The webserver at `machines/webserver/machine.yml` successfully processes the first HTTP request but fails on all subsequent requests with "Empty reply from server". After the first request, the server becomes completely unresponsive.

## Investigation Summary

### Test Environment vs Production
- **Tests**: All 25 tests pass, including resilience tests
- **Production**: Server fails on 2nd+ requests
- **Gap**: Tests use `MockSocket` which doesn't exhibit real TCP socket timing issues

### Timeline of Events

#### First Request (WORKS)
1. Client connects → `stream_socket_accept()` creates socket resource
2. Request data buffered → `ServerConnection` trigger dispatched
3. Child region spawned → enters `accept` state
4. Transitions to `processing` state → async handler executes
5. HTTP response + HTML template written to socket
6. Transitions to `close` state → `fclose($client)` called ✓
7. **Socket is now closed**

#### Second Request (FAILS)
1. Client connects → socket accepted
2. Request buffered → `ServerConnection` dispatched
3. Child region spawned → enters `accept` state
4. **PROBLEM**: Processing action early-returns due to invalid socket check
5. No HTTP response sent
6. Client receives "Empty reply from server"

## Root Cause: Async Action Early Return Bug

### The Bug (container.php:171-173)
```php
'request.action.processing' => function (object $trigger) {
    $client = $this->get('client');

    // Check if client socket is valid before proceeding
    if (!is_resource($client) || feof($client)) {
        return;  // ❌ BUG: Non-generator return in async action
    }
```

### Why This Breaks
The `processing` action is marked as `async: enabled: true` in machine.yml:
```yaml
- name: processing
  action:
    - run: !get request.action.processing
      async:
        enabled: true
```

When `async: enabled: true`, the AsyncFeature expects the action to be a **generator function** (must yield at least once). A plain `return` makes it a regular function, not a generator, which breaks the async execution model.

### The Symptom Chain
1. Socket validation fails (unclear why - race condition?)
2. Function returns without yielding
3. AsyncFeature gets confused by non-generator
4. State machine never transitions
5. No response written to client
6. Accept loop continues but child regions stall

## Secondary Issue: Socket Cleanup Race Condition

### Current Approach (container.php:46-55)
```php
foreach ($clients as $clientId => $client) {
    // Only add valid resources (may have been closed by child region)
    if (is_resource($client) && !feof($client)) {
        $read[] = $client;
    } else {
        // Clean up closed resources
        unset($clients[$clientId]);
        unset($buffers[$clientId]);
    }
}
```

### The Race Condition Window
```
T0: Parent checks is_resource($client) → TRUE ✓
T1: Parent checks feof($client) → FALSE ✓
T2: Parent adds $client to $read array
T3: **Child region calls fclose($client)** ← SOCKET INVALIDATED
T4: Parent calls stream_select($read, ...) → FAILS (invalid resource)
```

### Why Tests Pass
Test implementation (WebServerResilienceTestTrait.php:40-44):
```php
foreach ($clients as $clientId => $client) {
    if ($client->isClosed()) {  // MockSocket method
        unset($clients[$clientId]);
    }
}
```

MockSocket's `isClosed()` is synchronous and deterministic. Real PHP streams have unpredictable timing between `feof()` checks and actual socket closure.

## The Paradox

The code has TWO competing issues:
1. **Too defensive**: Early return in processing prevents valid requests from being processed
2. **Not defensive enough**: Race condition allows closed sockets into stream_select()

## Why Commit 2bf3e56 Didn't Fix It

That commit added:
- Socket validation before adding to `$read` array
- Error handling for `stream_select()` failures
- Resource checks before `get_resource_id()`

But it MISSED:
- The async/generator contract violation
- The fundamental timing gap between validation and usage
- Proper closed socket detection for PHP stream resources

## Additional Defensive Checks Added

The current code has multiple layers of defense:
1. **Line 46-55**: Pre-stream_select validation
2. **Line 58**: Error suppression on stream_select
3. **Line 62-67**: stream_select failure recovery
4. **Line 90-96**: Post-stream_select validation before read
5. **Line 171-173**: Processing action socket validation

Despite all these checks, the bug persists.

## Key Insights

### Why `is_resource() && !feof()` Fails
- PHP's `is_resource()` may still return TRUE after `fclose()` for a brief moment
- `feof()` requires a stream operation to update its state
- No atomic "is this socket actually usable?" check exists in PHP

### The Async Catch-22
- Without socket validation: Risk writing to closed socket
- With early return: Break async generator contract
- Need: Validation that still yields for async compatibility

### Test Blindspot
Tests validate behavior with cooperative mocks but miss real-world TCP socket timing issues. This is a fundamental gap between unit testing and integration testing with real I/O.

## Next Steps Required

1. **Immediate**: Fix async action to yield even on early return
2. **Core**: Redesign socket lifecycle coordination between parent/child regions
3. **Architectural**: Consider signaling mechanism for socket state changes
4. **Testing**: Add real socket integration tests (not mocks)

## Questions Requiring Investigation

1. Why does socket validation fail on 2nd+ requests specifically?
2. Is the socket actually invalid, or is detection incorrect?
3. Could the parent loop be reusing a stale client reference?
4. Does the buffer cleanup work correctly?
5. Is there a reference leak keeping dead sockets in `$clients`?

---

## Status Update

### Attempted Fix #1 (FAILED)
Added `yield` before return in processing action:
```php
if (!is_resource($client) || feof($client)) {
    yield; // CRITICAL: Must yield for async compatibility
    return;
}
```

**Result**: Tests pass, but production completely broken - NO requests work, not even the first one.

**Why**: The socket validation is ALWAYS failing, which means:
1. Socket stored in extended state is invalid/wrong reference
2. The validation check itself is the problem, not the early return
3. Adding yield just makes it fail gracefully instead of breaking async

### Root Cause Refined

The REAL bug is not the async yield issue - that's a symptom. The real bug is:

**The `$client` socket reference retrieved from extended state is invalid by the time `processing` action runs.**

This happens because:
1. Socket is stored in `accept` action: `$this->set('client', $client);`
2. Child region yields/transitions to `processing` state
3. Parent's accept loop continues, possibly invalidating the socket
4. `processing` action retrieves: `$client = $this->get('client');`
5. Socket reference is stale/closed/invalid
6. Validation fails, processing aborts

### The Real Solution

DO NOT validate socket in processing action. The socket SHOULD be valid because:
1. It's the child region's own socket
2. Parent shouldn't close it (only child closes on exit)
3. If it's invalid, that's a bigger problem elsewhere

Remove the defensive check entirely:
```php
'request.action.processing' => function (object $trigger) {
    $client = $this->get('client');
    // DO NOT validate - trust the socket is valid
    // If it's not, investigate why parent is closing child's socket

    $headers = $this->get('headers');
    // ... rest of processing
```

### Next Steps for Tomorrow

1. **Remove socket validation from processing action completely**
2. **Add debug logging** to track socket lifecycle:
   - When socket is stored in accept
   - When socket is retrieved in processing
   - Parent's socket cleanup operations
3. **Identify why socket becomes invalid** between accept and processing
4. **Fix parent/child socket ownership** - parent shouldn't touch child's socket
5. **Only then** add back defensive validation if truly needed

---

**Status**: Immediate fix attempted but revealed deeper issue. Socket lifecycle between parent/child regions is broken. Needs architectural fix, not defensive programming.
