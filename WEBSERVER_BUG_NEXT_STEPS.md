# Webserver Bug - Next Steps for Tomorrow

## Current Status

**Code State**: Reverted to baseline (working in tests, broken in production)
**Analysis**: Complete - documented in `WEBSERVER_BUG_ROOT_CAUSE_ANALYSIS.md`
**Specs**: ✅ Thorough and complete in `specs/machines/webserver.yaml`
**Tests**: ✅ All 25 tests passing

## The Core Issue

Socket stored in child region's extended state becomes invalid between `accept` and `processing` actions. The defensive socket validation check (lines 170-175 in container.php) is currently commented out because:
1. With validation: ALL requests fail (socket always invalid)
2. Without validation: First request works, subsequent requests fail

This indicates a fundamental problem with socket lifecycle management between parent and child regions.

## Investigation Plan for Tomorrow

### Step 1: Add Debug Logging
Add logging to track socket lifecycle:

```php
'request.action.accept' => function (ServerConnection $connection) {
    $client = $connection->client;
    $resourceId = get_resource_id($client);
    error_log("[ACCEPT] Socket $resourceId stored in child region");
    $this->set('client', $client);
    // ... rest of accept
},

'request.action.processing' => function (object $trigger) {
    $client = $this->get('client');
    $valid = is_resource($client) ? 'VALID' : 'INVALID';
    $eof = is_resource($client) && feof($client) ? 'EOF' : 'OPEN';
    error_log("[PROCESSING] Socket retrieved: $valid, $eof");

    // Continue without validation for now
    // ... rest of processing
},
```

Also add logging in parent's accept loop when it cleans up sockets.

### Step 2: Run Server and Analyze Logs
```bash
ddev exec php machines/webserver/machine.php 2>webserver-debug.log &
# Send requests
ddev exec curl http://0.0.0.0:8080/test1
ddev exec curl http://0.0.0.0:8080/test2
# Analyze logs
cat webserver-debug.log
```

### Step 3: Identify the Socket Killer
Look for patterns in logs:
- Is the same socket ID being reused?
- Does parent clean up child's socket?
- Is socket invalidated during yield/transition?
- Timing of socket closure vs. processing retrieval

### Step 4: Fix Socket Ownership
Based on findings, likely fixes:
1. **Parent shouldn't track child sockets**: Remove child sockets from `$clients` array
2. **Use different socket ID scheme**: Prevent parent/child collisions
3. **Pass socket in trigger**: Instead of storing in extended state
4. **Validate socket ownership**: Parent checks before cleanup

### Step 5: Re-enable Validation (If Needed)
Once socket lifecycle is fixed, can add back defensive validation:
```php
if (!is_resource($client) || feof($client)) {
    yield; // Must yield for async
    error_log("[PROCESSING] Socket invalid - aborting");
    return;
}
```

## Key Questions to Answer

1. **Why does the socket become invalid?**
   - Parent closes it prematurely?
   - Extended state corrupts the reference?
   - PHP garbage collection?

2. **Why does first request work?**
   - Different code path?
   - Timing window before parent interferes?
   - Socket still valid on first iteration?

3. **What's different between test and production?**
   - MockSocket vs real TCP sockets
   - Timing of cooperative yields
   - Resource cleanup behavior

## Files Modified

- `machines/webserver/src/container.php` - Socket validation commented out with TODO
- `WEBSERVER_BUG_ROOT_CAUSE_ANALYSIS.md` - Complete analysis
- `WEBSERVER_BUG_NEXT_STEPS.md` - This file

## Important Notes

- **DO NOT** uncomment socket validation until root cause is fixed
- **DO NOT** add defensive checks - fix the architecture
- **DO** add logging before changing any logic
- **DO** verify tests still pass after each change

## Success Criteria

Server can handle multiple sequential requests:
```bash
ddev exec curl http://0.0.0.0:8080/test1  # ✓ Works
ddev exec curl http://0.0.0.0:8080/test2  # ✓ Works
ddev exec curl http://0.0.0.0:8080/test3  # ✓ Works
ddev exec curl http://0.0.0.0:8080/test4  # ✓ Works
```

All tests continue to pass:
```bash
ddev exec vendor/bin/phpunit tests/PHPUnit/E2E/WebServer/
```
