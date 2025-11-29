# Testing Skill - Testing Infrastructure & Patterns

## Purpose

This skill covers **testing strategies, patterns, and infrastructure** for the Regions project. It provides guidance on unit tests, integration tests, E2E tests, and the custom test runner system.

## Test Organization

### Directory Structure

```
tests/PHPUnit/
├── Unit/[Group]/[Component]/[Behavior]Test.php    # Unit tests
├── Integration/[Group]/[Component]/                # Integration tests
└── E2E/                                            # Machine tests (end-to-end)
    ├── ApplicationTestCase.php                     # Base class for machines
    ├── AsyncMachineTestCase.php                   # Base for async machines
    ├── NetworkMachineTestCase.php                 # Base for network machines
    ├── Support/                                    # Mock infrastructure
    │   ├── MockSocket.php
    │   └── MockConnection.php
    └── [MachineName]/Basic/[Test].php             # Machine-specific tests
```

### Test Type Selection

| Test Type | When to Use | Location |
|-----------|-------------|----------|
| **Unit** | Single class/method behavior | `tests/PHPUnit/Unit/` |
| **Integration** | Multiple components interaction | `tests/PHPUnit/Integration/` |
| **E2E** | Complete machine behavior | `tests/PHPUnit/E2E/` |

## Test Creation Guidelines

### One Spec = One Test Class

**Critical rule**: Maintain 1:1 mapping between specs and test classes.

```php
// ✅ GOOD: One test class per spec
class StateCheckTest extends TestCase { ... }  // For "Region can check if in state" spec

// ❌ BAD: Multiple specs in one test class
class RegionTest extends TestCase {
    public function testStateCheck() { ... }
    public function testTransitions() { ... }
    public function testLifecycle() { ... }
}
```

### Test Template

```php
<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\[Group]\[Component];

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: [Exact text from YAML spec]
 */
#[Group('component'), Group('feature')]
class DescriptiveTest extends TestCase
{
    /**
     * @test
     */
    public function behaviorDescription(): void
    {
        // Arrange: Set up test conditions
        $subject = new Subject();
        
        // Act: Perform the action being tested
        $result = $subject->doSomething();
        
        // Assert: Verify expected outcome
        $this->assertTrue($result);
    }
}
```

### PHPUnit Attributes

Use attributes for test organization and filtering:

```php
#[Group('region'), Group('lifecycle')]        // Component + feature grouping
#[Group('transitions'), Group('guards')]      // Multiple logical groups
```

**Running specific groups:**
```bash
ddev exec vendor/bin/phpunit --group=transitions
ddev exec vendor/bin/phpunit --group=lifecycle --group=region
```

## Testing Patterns

### ChainMail Boot Requirement

**Critical pattern**: ChainMail middleware doesn't execute until `boot()` is called.

```php
// ❌ WRONG: Middleware not executed
$chainMail = new ChainMail();
$feature($chainMail);
$service = $chainMail->get(MyService::class);  // FAILS: Service not registered yet

// ✅ CORRECT: Boot to execute middleware
$chainMail = new ChainMail();
$feature($chainMail);
$chainMail->boot();  // Executes all registered middleware
$service = $chainMail->get(MyService::class);  // SUCCESS: Service available
```

**Common workflow:**
1. Create ChainMail instance
2. Register features/middleware
3. Call `boot()`
4. Assert expected state

### State Machine Construction Pattern

```php
private function buildRegion(): Region
{
    return (new RegionBuilder())
        ->enableFeatures(new TransitionsFeature())
        ->setStates('idle', 'processing', 'done')
        ->markInitial('idle')
        ->markFinal('done')
        ->addBuildStep(new AddTransition('idle', 'processing', 
            fn(object $t): bool => $t->ready
        ))
        ->onEnter('processing', fn(object $t) => $this->recordAction('start'))
        ->build();
}
```

### Guard Signature Testing

Guards must accept a trigger parameter:

```php
// ✅ CORRECT: Guard accepts trigger
fn(object $trigger): bool => $trigger->isValid

// ❌ WRONG: Missing parameter causes error
fn(): bool => true  // Error: "Required Parameter 0 not declared"
```

## Machine Testing (E2E)

### Base Test Classes

#### ApplicationTestCase

Base class for all machine tests:

```php
abstract class ApplicationTestCase extends TestCase
{
    protected function region(): Region
    {
        // Load from yaml() and container()
    }
    
    abstract protected function yaml(): string;
    abstract protected function container(): iterable;
}
```

#### AsyncMachineTestCase

For machines using async features:

```php
class AsyncMachineTestCase extends ApplicationTestCase
{
    /**
     * Tick until condition is true or maxTicks reached.
     */
    protected function tickUntil(Region $region, callable $condition, int $maxTicks = 100): void;
    
    /**
     * Tick N times.
     */
    protected function tickN(Region $region, int $ticks): void;
    
    /**
     * Count active tasks in region.
     */
    protected function countActiveTasks(Region $region): int;
    
    /**
     * Assert region has active tasks.
     */
    protected function assertHasActiveTasks(Region $region, string $message = ''): void;
}
```

#### NetworkMachineTestCase

For machines with network I/O:

```php
class NetworkMachineTestCase extends AsyncMachineTestCase
{
    protected MockSocket $mockSocket;
    
    /**
     * Queue a mock HTTP request.
     */
    protected function queueHttpRequest(
        string $method = 'GET',
        string $uri = '/',
        array $headers = [],
        string $body = ''
    ): MockConnection;
    
    /**
     * Assert connection was closed.
     */
    protected function assertConnectionClosed(MockConnection $connection, string $message = ''): void;
    
    /**
     * Assert socket is non-blocking.
     */
    protected function assertSocketNonBlocking(string $message = ''): void;
}
```

### Machine Testing Pattern

```php
<?php

namespace Noem\State\Tests\PHPUnit\E2E\WebServer\Basic;

use Noem\State\Tests\PHPUnit\E2E\NetworkMachineTestCase;
use PHPUnit\Framework\Attributes\Test;

class HandleHttpRequestTest extends NetworkMachineTestCase
{
    #[Test]
    public function machineProcessesHttpRequest(): void
    {
        // Arrange: Set up machine with mocked dependencies
        $region = $this->region();
        $mockConnection = $this->queueHttpRequest('GET', '/test');
        
        // Act: Execute machine behavior
        $this->tickN($region, 10);
        
        // Assert: Verify observable outcomes
        $this->assertConnectionClosed($mockConnection);
        $this->assertTrue($region->isInState('idle'));
    }
    
    protected function yaml(): string
    {
        return file_get_contents(__DIR__ . '/../../../machines/webserver/machine.yml');
    }
    
    protected function container(): iterable
    {
        return [
            'socket' => fn() => $this->mockSocket,
            'action.accept_connection' => function ($trigger) {
                // Use mock instead of real socket
                $this->mockSocket->accept();
            },
        ];
    }
}
```

### Mock Infrastructure

#### MockSocket

Simulates socket operations:

```php
$mockSocket = new MockSocket();
$mockSocket->queueConnection($mockConnection);
$connection = $mockSocket->accept();  // Returns queued connection
```

#### MockConnection

Simulates client connections:

```php
$mockConnection = new MockConnection("GET /test HTTP/1.1\r\n\r\n");
$data = $mockConnection->read(1024);
$mockConnection->write("HTTP/1.1 200 OK\r\n\r\nHello");
$mockConnection->close();
```

## Test Execution

### Running Tests

```bash
# Single test class
ddev exec vendor/bin/phpunit tests/PHPUnit/Unit/Region/StateCheckTest.php

# All tests in directory
ddev exec vendor/bin/phpunit tests/PHPUnit/Unit/Region/

# By group
ddev exec vendor/bin/phpunit --group=transitions

# Specific test method
ddev exec vendor/bin/phpunit --filter=testGuardReceivesTrigger

# E2E tests
ddev exec vendor/bin/phpunit tests/PHPUnit/E2E/WebServer/Basic/
```

### Using Test Runner

Run spec-defined tests via middleware test runner:

```bash
# Run specific spec suite
ddev exec machines/middleware-test-runner/run.sh --spec=specs/core/region.yaml

# With flags
ddev exec machines/middleware-test-runner/run.sh \
  --spec=specs/features/transitions.yaml \
  --verbose \
  --stop-on-failure
```

### Full Regression

```bash
# Run all specs (atlas command)
ddev atlas

# With output control
ddev atlas --quiet              # Errors only
ddev atlas --verbose            # Full output
ddev atlas --stop-on-failure    # Stop at first failure
```

## Assertion Patterns

### State Assertions

```php
// State checks
$this->assertTrue($region->isInState('processing'));
$this->assertFalse($region->isFinal());

// Multiple possible states
$this->assertTrue(
    $region->isInState('processing') || $region->isInState('waiting'),
    'Region should be in processing or waiting state'
);
```

### Action Recording

```php
private array $recordedActions = [];

private function recordAction(string $action): void
{
    $this->recordedActions[] = $action;
}

// In test
$region->onEnter('processing', fn($t) => $this->recordAction('enter:processing'));
$region->trigger($event);

$this->assertContains('enter:processing', $this->recordedActions);
$this->assertCount(1, $this->recordedActions);
```

### Execution Order Verification

```php
public function testActionsExecuteBeforeTransitions(): void
{
    $sequence = [];
    
    $region = (new RegionBuilder())
        ->setStates('A', 'B')
        ->markInitial('A')
        ->on('A', 'moveToB', fn($t) => $sequence[] = 'action')
        ->addBuildStep(new AddTransition('A', 'B', 
            fn($t): bool => ($sequence[] = 'guard') && true
        ))
        ->onEnter('B', fn($t) => $sequence[] = 'enter:B')
        ->build();
    
    $region->trigger((object)['type' => 'moveToB']);
    
    $this->assertEquals(['action', 'guard', 'enter:B'], $sequence);
}
```

### Task Lifecycle Verification (Async)

```php
public function testTaskCreatedAndCleaned(): void
{
    $region = $this->buildAsyncRegion();
    
    // Initial state: no tasks
    $this->assertCount(0, $this->countActiveTasks($region));
    
    // Trigger creates task
    $region->trigger($createTaskEvent);
    $this->assertHasActiveTasks($region);
    
    // Tick until completion
    $this->tickUntil($region, fn() => !$this->countActiveTasks($region));
    
    // Final state: task cleaned up
    $this->assertCount(0, $this->countActiveTasks($region));
}
```

## Testing Critical Behaviors

### Hierarchical State Updates

```php
public function testChildRegionUpdatesStateOnParentTrigger(): void
{
    $parent = (new RegionBuilder())
        ->setStates('active')
        ->markInitial('active')
        ->build();
    
    $child = (new RegionBuilder())
        ->setStates('idle', 'busy')
        ->markInitial('idle')
        ->addBuildStep(new AddTransition('idle', 'busy', fn($t): bool => true))
        ->build();
    
    $parent->connect($child);
    $parent->trigger(new \stdClass());
    
    $this->assertTrue($child->isInState('busy'), 'Child should transition');
}
```

### Feature Order Dependencies

```php
public function testAsyncFeatureRequiresExtendedState(): void
{
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('context › resolvers');
    
    // ❌ Wrong order: AsyncFeature before ExtendedState
    $region = (new RegionBuilder())
        ->enableFeatures(
            new RegionLoader(),
            new AsyncFeature(),      // Tries to extend schema
            new ExtendedState()      // Creates schema (too late!)
        )
        ->build(['loader' => ['array' => [
            'context' => ['resolvers' => []]
        ]]]);
}

public function testCorrectFeatureOrder(): void
{
    // ✅ Correct order
    $region = (new RegionBuilder())
        ->enableFeatures(
            new RegionLoader(),
            new ExtendedState(),     // Creates schema first
            new AsyncFeature()       // Extends existing schema
        )
        ->build(['loader' => ['array' => [
            'context' => ['resolvers' => []]
        ]]]);
    
    $this->assertInstanceOf(Region::class, $region);
}
```

### Guard Validation

```php
public function testGuardMustAcceptTrigger(): void
{
    $this->expectException(\TypeError::class);
    $this->expectExceptionMessage('Required Parameter 0 not declared');
    
    // ❌ Guard missing parameter
    (new RegionBuilder())
        ->addBuildStep(new AddTransition('A', 'B', fn(): bool => true))
        ->build();
}

public function testGuardReceivesTrigger(): void
{
    $receivedTrigger = null;
    
    $region = (new RegionBuilder())
        ->setStates('A', 'B')
        ->markInitial('A')
        ->addBuildStep(new AddTransition('A', 'B', 
            function(object $t) use (&$receivedTrigger): bool {
                $receivedTrigger = $t;
                return true;
            }
        ))
        ->build();
    
    $trigger = (object)['value' => 42];
    $region->trigger($trigger);
    
    $this->assertSame($trigger, $receivedTrigger);
}
```

## Debugging Failed Tests

### Step 1: Read the Spec

Before debugging, always read the spec:
```bash
cat specs/[group]/[component].yaml | grep -A 10 "acceptanceCriteria: [failing behavior]"
```

### Step 2: Verify Spec-Test Alignment

Ensure the test actually tests what the spec describes:
- Test name matches acceptance criterion
- Assertions verify the specified behavior
- No testing of implementation details

### Step 3: Check for Common Issues

- [ ] ChainMail booted before assertions?
- [ ] Guard signature includes trigger parameter?
- [ ] Feature order correct (ExtendedState before AsyncFeature)?
- [ ] Using isset() for Helpers, not hasHelper()?
- [ ] Self-transitions execute full lifecycle?

### Step 4: Isolate the Problem

```bash
# Run just the failing test with verbose output
ddev exec vendor/bin/phpunit --testdox --verbose tests/PHPUnit/.../FailingTest.php

# Run with debug output
ddev exec vendor/bin/phpunit --debug tests/PHPUnit/.../FailingTest.php
```

### Step 5: Fix Code, Not Test

**Remember**: If the test correctly implements the spec, fix the code, not the test.

## Quality Assurance

### Before Committing

```bash
# Run all tests
ddev exec vendor/bin/phpunit

# Run quality checks
ddev exec composer quality

# Run full regression
ddev atlas
```

All must pass before commit.

### Coverage Considerations

- Aim for high coverage of contract specs
- Constraint specs should be covered
- Detail specs coverage is optional
- Integration tests often provide coverage for multiple unit specs

## When to Load This Skill

**Always load when:**
- Writing tests for new features
- Debugging failing tests
- Reviewing test organization
- Working on test infrastructure
- User asks about testing patterns

**Combine with:**
- **specification** skill - for spec-to-test alignment
- **core-development** skill - for implementation context
- **region-development** skill - for machine testing patterns
