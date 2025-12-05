# Testing Skill - Testing Infrastructure Protocol

## ⚡ CRITICAL TESTING RULES

**ONE SPEC = ONE TEST CLASS** - Maintain strict 1:1 mapping. Violation = consolidate or split immediately.

---

## 🚫 ABSOLUTE RULES - NEVER VIOLATE

| Rule | Violation = Consequence |
|------|-------------------------|
| **ONE SPEC = ONE TEST CLASS** | STOP → Consolidate or split to restore 1:1 |
| **ChainMail MUST boot() before assertions** | STOP → Add boot() call before assertions |
| **Guards MUST accept trigger parameter** | STOP → Add `fn(object $trigger)` signature |
| **ExtendedState BEFORE AsyncFeature** | STOP → Reorder features |
| **Use isset() for Helpers, NOT hasHelper()** | STOP → Replace with isset() |

---

## 📂 TEST ORGANIZATION PROTOCOL

### Directory Structure (MANDATORY)

```
tests/PHPUnit/
├── Unit/[Group]/[Component]/[Behavior]Test.php    # Single class/method
├── Integration/[Group]/[Component]/                # Multiple components
└── E2E/                                            # Complete machines
    ├── ApplicationTestCase.php                     # Base for all machines
    ├── AsyncMachineTestCase.php                   # Base for async
    ├── NetworkMachineTestCase.php                 # Base for network
    ├── Support/                                    # Mock infrastructure
    │   ├── MockSocket.php
    │   └── MockConnection.php
    └── [MachineName]/Basic/[Test].php             # Machine tests
```

### Test Type Selection Matrix

| Scenario | Test Type | Location | Base Class |
|----------|-----------|----------|------------|
| Single class/method behavior | Unit | `Unit/[Group]/[Component]/` | `TestCase` |
| Multiple components interacting | Integration | `Integration/[Group]/[Component]/` | `TestCase` |
| Complete machine behavior | E2E | `E2E/[MachineName]/Basic/` | `ApplicationTestCase` |
| Async machine | E2E | `E2E/[MachineName]/Basic/` | `AsyncMachineTestCase` |
| Network machine | E2E | `E2E/[MachineName]/Basic/` | `NetworkMachineTestCase` |

---

## ✍️ TEST CREATION PROTOCOL

### MANDATORY Template

**EXECUTE THIS FOR EVERY TEST:**

```php
<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\[Group]\[Component];

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: [EXACT TEXT FROM YAML SPEC]
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

        // Act: Perform action being tested
        $result = $subject->doSomething();

        // Assert: Verify expected outcome
        $this->assertTrue($result);
    }
}
```

**MANDATORY ELEMENTS:**
- ✅ PHPDoc with EXACT acceptance criteria from YAML
- ✅ PHPUnit attributes for grouping
- ✅ Arrange → Act → Assert structure
- ✅ Descriptive test method name

---

## 🔧 CRITICAL TESTING PATTERNS

### Pattern 1: ChainMail Boot Requirement

**CRITICAL**: ChainMail middleware does NOT execute until `boot()` called.

**WRONG:**
```php
$chainMail = new ChainMail();
$feature($chainMail);
$service = $chainMail->get(MyService::class);  // FAILS
```

**CORRECT:**
```php
$chainMail = new ChainMail();
$feature($chainMail);
$chainMail->boot();  // MANDATORY
$service = $chainMail->get(MyService::class);  // SUCCESS
```

**Protocol:**
```
STEP 1: Create ChainMail instance
STEP 2: Register features/middleware
STEP 3: Call boot() - MANDATORY
STEP 4: Assert expected state
```

### Pattern 2: Guard Signature Requirement

**Guards MUST accept trigger parameter.**

**WRONG:**
```php
fn(): bool => true  // Error: "Required Parameter 0 not declared"
```

**CORRECT:**
```php
fn(object $trigger): bool => $trigger->isValid
```

**Verification test:**
```php
public function testGuardMustAcceptTrigger(): void
{
    $this->expectException(\TypeError::class);
    $this->expectExceptionMessage('Required Parameter 0 not declared');

    (new RegionBuilder())
        ->addBuildStep(new AddTransition('A', 'B', fn(): bool => true))
        ->build();
}
```

### Pattern 3: State Machine Construction

**Standard pattern:**

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

---

## 🎰 MACHINE TESTING PROTOCOL

### Base Test Class Selection

**Execute this decision tree:**

```
MACHINE TYPE?
├─ Standard (no async, no network)
│  └─ USE: ApplicationTestCase
│
├─ Uses AsyncFeature
│  └─ USE: AsyncMachineTestCase
│     └─ PROVIDES: tickUntil(), tickN(), countActiveTasks()
│
└─ Uses network I/O
   └─ USE: NetworkMachineTestCase
      └─ PROVIDES: queueHttpRequest(), assertConnectionClosed()
```

### ApplicationTestCase Protocol

**MANDATORY implementation:**

```php
abstract class YourMachineTest extends ApplicationTestCase
{
    protected function yaml(): string
    {
        return file_get_contents(__DIR__ . '/../../../machines/machine-name/machine.yml');
    }

    abstract protected function container(): iterable
    {
        return [
            'service.name' => fn() => $this->mockService,
            'action.handler' => function ($trigger) {
                $this->recordAction('handler', $trigger);
            },
        ];
    }
}
```

### AsyncMachineTestCase Methods

**Available methods:**

```php
// Tick until condition true or maxTicks reached
protected function tickUntil(Region $region, callable $condition, int $maxTicks = 100): void;

// Tick N times
protected function tickN(Region $region, int $ticks): void;

// Count active tasks
protected function countActiveTasks(Region $region): int;

// Assert has active tasks
protected function assertHasActiveTasks(Region $region, string $message = ''): void;
```

**Usage pattern:**

```php
public function testAsyncBehavior(): void
{
    $region = $this->region();

    // Trigger creates task
    $region->trigger($createTaskEvent);
    $this->assertHasActiveTasks($region);

    // Tick until completion
    $this->tickUntil($region, fn() => !$this->countActiveTasks($region));

    // Verify cleanup
    $this->assertCount(0, $this->countActiveTasks($region));
}
```

### NetworkMachineTestCase Methods

**Available methods:**

```php
// Queue mock HTTP request
protected function queueHttpRequest(
    string $method = 'GET',
    string $uri = '/',
    array $headers = [],
    string $body = ''
): MockConnection;

// Assert connection closed
protected function assertConnectionClosed(MockConnection $connection, string $message = ''): void;

// Assert socket non-blocking
protected function assertSocketNonBlocking(string $message = ''): void;
```

**Usage pattern:**

```php
public function testHttpRequest(): void
{
    $region = $this->region();
    $mockConnection = $this->queueHttpRequest('GET', '/test');

    $this->tickN($region, 10);

    $this->assertConnectionClosed($mockConnection);
    $this->assertTrue($region->isInState('idle'));
}
```

---

## 🔍 ASSERTION PATTERNS

### State Assertions

```php
// Single state check
$this->assertTrue($region->isInState('processing'));
$this->assertFalse($region->isFinal());

// Multiple possible states
$this->assertTrue(
    $region->isInState('processing') || $region->isInState('waiting'),
    'Region should be in processing or waiting state'
);
```

### Action Recording Pattern

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

---

## 🚀 TEST EXECUTION PROTOCOL

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
# Run all specs
ddev atlas

# With output control
ddev atlas --quiet              # Errors only
ddev atlas --verbose            # Full output
ddev atlas --stop-on-failure    # Stop at first failure
```

---

## 🚨 CRITICAL BEHAVIORS TO TEST

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

---

## 🐛 DEBUGGING FAILED TESTS PROTOCOL

**EXECUTE IN ORDER:**

```
STEP 1: Read the spec
  └─ RUN: cat specs/[group]/[component].yaml | grep -A 10 "acceptanceCriteria: [failing behavior]"

STEP 2: Verify spec-test alignment
  ├─ Test name matches acceptance criterion?
  ├─ Assertions verify specified behavior?
  └─ Not testing implementation details?

STEP 3: Check common issues
  ├─ [ ] ChainMail booted before assertions?
  ├─ [ ] Guard signature includes trigger parameter?
  ├─ [ ] Feature order correct (ExtendedState before AsyncFeature)?
  ├─ [ ] Using isset() for Helpers, not hasHelper()?
  └─ [ ] Self-transitions execute full lifecycle?

STEP 4: Isolate the problem
  └─ RUN: ddev exec vendor/bin/phpunit --testdox --verbose [test-path]

STEP 5: Fix code, NOT test
  └─ If test correctly implements spec → fix code, not test
```

---

## ✅ PRE-COMMIT QUALITY PROTOCOL

**MANDATORY before ANY commit:**

```
CHECK 1: Run all tests
  └─ RUN: ddev exec vendor/bin/phpunit
  └─ MUST: All pass

CHECK 2: Quality checks
  └─ RUN: ddev exec composer quality
  └─ MUST: All pass

CHECK 3: Full regression
  └─ RUN: ddev atlas
  └─ MUST: All pass
```

**ANY FAILURE = DO NOT COMMIT**

---

## 🔗 SKILL INTEGRATION

**Load with these skills:**
- **specification** - Spec-to-test alignment
- **core-development** - Implementation context
- **region-development** - Machine testing patterns

---

## 📚 COMMON TESTING SCENARIOS

### Task Lifecycle Verification (Async)

```php
public function testTaskCreatedAndCleaned(): void
{
    $region = $this->buildAsyncRegion();

    // Initial: no tasks
    $this->assertCount(0, $this->countActiveTasks($region));

    // Trigger creates task
    $region->trigger($createTaskEvent);
    $this->assertHasActiveTasks($region);

    // Tick until completion
    $this->tickUntil($region, fn() => !$this->countActiveTasks($region));

    // Final: task cleaned up
    $this->assertCount(0, $this->countActiveTasks($region));
}
```

### Guard Validation

```php
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

### Mock Infrastructure Usage

```php
// MockSocket
$mockSocket = new MockSocket();
$mockSocket->queueConnection($mockConnection);
$connection = $mockSocket->accept();

// MockConnection
$mockConnection = new MockConnection("GET /test HTTP/1.1\r\n\r\n");
$data = $mockConnection->read(1024);
$mockConnection->write("HTTP/1.1 200 OK\r\n\r\nHello");
$mockConnection->close();
```
