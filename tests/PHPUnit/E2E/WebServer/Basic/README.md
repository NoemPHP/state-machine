# WebServer Machine - Basic Tests

End-to-end tests for the async webserver machine demonstrating spec-driven development for complete state machine applications.

## Test Structure

Tests are organized by spec features from `specs/machines/webserver.yaml`:

### Server Lifecycle (2 tests)
- [x] `ServerSocketCreationTest` - Non-blocking socket creation
- [x] `ServerSocketStorageTest` - Socket storage in extended state

### Connection Spawning (3 tests)  
- [x] `ConnectionSpawningTest` - Child region creation
- [ ] `ConnectionTriggerPropagationTest` - Trigger passing to spawned regions
- [ ] `MultipleConnectionSpawningTest` - Concurrent connection handling

### Connection Lifecycle (5 tests)
- [x] `ConnectionInitialStateTest` - Accept state initialization
- [ ] `AcceptToProcessingTransitionTest` - State transition
- [ ] `ClientSocketStorageTest` - Client socket storage
- [ ] `ProcessingToCloseTransitionTest` - Response completion
- [ ] `SocketCleanupTest` - Resource cleanup

### Async Behavior (3 tests)
- [x] `ServerYieldBehaviorTest` - Server cooperative yielding
- [ ] `ConnectionYieldBehaviorTest` - Connection cooperative yielding  
- [ ] `ConcurrentProgressTest` - True async concurrency

**Progress: 5/13 tests implemented** ✅

## Testing Infrastructure

### Base Classes
- `ApplicationTestCase` - Core machine testing utilities
- `AsyncMachineTestCase` - Async/coroutine helpers (`tickUntil`, `tickN`, `countActiveTasks`)
- `NetworkMachineTestCase` - Mock socket infrastructure

### Mock Objects
- `MockSocket` - Simulates `stream_socket_*` operations
- `MockConnection` - Controllable HTTP connections

### Key Patterns

#### Container Mocking
```php
public function container(): iterable
{
    $mockSocket = $this->mockSocket;
    
    return [
        'handler' => function (object $trigger) use ($mockSocket) {
            // Use mock instead of real I/O
            $mockSocket->setBlocking(false);
            $this->set('server', $mockSocket);
        },
    ];
}
```

#### Async Testing
```php
#[Test]
public function testAsyncBehavior(): void
{
    $region = $this->region();
    
    // Execute for N ticks
    $this->tickN($region, 10);
    
    // Or tick until condition met
    $this->tickUntil($region, fn() => $region->isInState('done'), maxTicks: 100);
    
    // Verify async tasks active
    $this->assertHasActiveTasks($region);
}
```

## Running Tests

```bash
# Single test
ddev exec vendor/bin/phpunit tests/PHPUnit/E2E/WebServer/Basic/ServerSocketCreationTest.php

# All basic webserver tests
ddev exec vendor/bin/phpunit tests/PHPUnit/E2E/WebServer/Basic/

# With group filters
ddev exec vendor/bin/phpunit --group webserver
ddev exec vendor/bin/phpunit --group server-lifecycle
```

## Next Steps

1. **Complete remaining 8 tests** following established patterns
2. **Add ServerConnection class** to shared location (currently in machine.php)
3. **Enhance mock infrastructure** if needed for complex scenarios
4. **Integration with spec runner** once machine spec support added

## Notes

- Tests use **mocked sockets** - no real network I/O
- Each test is **independent** and can run in isolation
- Focus on **observable behavior**, not implementation details
- Follow **boy scout rule** - improve as you go
