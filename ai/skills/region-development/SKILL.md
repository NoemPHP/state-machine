# Region Development Skill - State Machine Application Protocol

## ⚡ CRITICAL MACHINE DEVELOPMENT RULES

**Guards MUST accept trigger** - `fn($t)` signature mandatory. ExtendedState MUST come before AsyncFeature. Initial state MANDATORY.

---

## 🚫 ABSOLUTE RULES - NEVER VIOLATE

| Rule | Violation = Consequence |
|------|-------------------------|
| **Guards MUST accept trigger: fn($t)** | STOP → Add trigger parameter |
| **Handlers MUST be in container** | STOP → Add to container configuration |
| **Initial state MUST be defined** | STOP → Add `initial:` field |
| **ExtendedState BEFORE AsyncFeature** | STOP → Reorder features list |
| **NO circular dependencies in container** | STOP → Refactor dependencies |

---

## 📋 YAML MACHINE SCHEMA REFERENCE

### Basic Structure (MANDATORY)

```yaml
name: machine_name
states:
  - name: state_name
    on:
      event_name:
        - handler: action.handler_name
    onEnter:
      - handler: action.enter_handler
    onExit:
      - handler: action.exit_handler

transitions:
  - from: state_a
    to: state_b
    guard: !php "return fn($t) => $t->ready;"

initial: initial_state_name  # MANDATORY
final:
  - final_state_name
```

### State Definition

**Elements:**
- `name` - State identifier (string) - MANDATORY
- `on` - Event → action mappings
- `onEnter` - Callbacks when entering state
- `onExit` - Callbacks when exiting state

**Example:**

```yaml
states:
  - name: idle
    on:
      start:
        - handler: action.start_processing
    onEnter:
      - handler: action.log_entry
      - handler: action.initialize_resources
    onExit:
      - handler: action.cleanup
```

### Transitions

**Guard requirements:**
- MUST accept trigger parameter: `fn($t)`
- MUST return boolean
- First matching guard wins

**Example:**

```yaml
transitions:
  - from: idle
    to: processing
    guard: !php "return fn($t) => $t->type === 'start';"

  - from: processing
    to: done
    guard: !php "return fn($t) => $t->status === 'complete';"

  - from: processing
    to: error
    guard: !php "return fn($t) => isset($t->error);"
```

### Initial and Final States (MANDATORY)

```yaml
initial: idle  # Machine starts here - MANDATORY

final:  # Machine stops at any of these
  - done
  - error
  - cancelled
```

### Extended State (Context)

**Shared data across states:**

```yaml
context:
  counter: 0
  items: []
  config:
    timeout: 30
    maxRetries: 3
```

**Access in actions:**

```php
public function __invoke(object $trigger, array $context): void
{
    $counter = $context['counter'];
    $timeout = $context['config']['timeout'];
}
```

### Hierarchical States (Regions)

**States containing child regions:**

```yaml
states:
  - name: processing
    regions:
      - states:
          - name: validating
          - name: transforming
          - name: persisting
        initial: validating
        transitions:
          - from: validating
            to: transforming
            guard: !php "return fn($t) => $t->valid;"
```

### Orthogonal Regions (Parallel States)

**Multiple regions executing simultaneously:**

```yaml
states:
  - name: active
    regions:
      # Region 1: Data processing
      - states:
          - name: fetching
          - name: processing
        initial: fetching

      # Region 2: UI updates
      - states:
          - name: rendering
          - name: updating
        initial: rendering
```

### Spawn Configuration

**Dynamic child region creation:**

```yaml
states:
  - name: coordinator
    spawn:
      - guard: !php "return fn($t) => $t->needsWorker;"
        region:
          states:
            - name: worker_idle
            - name: worker_busy
          initial: worker_idle
```

**Spawn modes:**
- `PERSISTENT` - Child survives parent state changes (default)
- `DYNAMIC` - Child destroyed when parent changes state

### Async Resolvers

**Lazy-loaded data:**

```yaml
context:
  resolvers:
    userData:
      handler: service.user_repository
      cacheKey: user_{{ userId }}

    configuration:
      handler: service.config_loader
      cacheKey: app_config
```

**Access:**

```php
public function __invoke(object $trigger, array $context): void
{
    $user = $context['userData'];  // Lazy-loaded on first access
}
```

### Templates

**Dynamic content generation:**

```yaml
templates:
  greeting: "Hello, {{ name }}! Welcome to {{ app.name }}."
  error: "Error {{ code }}: {{ message }}"
```

### AI Integration

```yaml
ai:
  prompts:
    welcome:
      prompt: "Generate a welcoming message for a user named {{ name }}"
      model: claude-sonnet-4-20250514
      maxTokens: 100
```

---

## 🔧 CONTAINER CONFIGURATION

### Service Definition

```yaml
container:
  - id: service.logger
    class: Psr\Log\NullLogger

  - id: service.database
    class: App\Database
    arguments:
      - '@service.config'
      - dsn: "mysql:host=localhost"

  - id: action.process_request
    class: App\Actions\ProcessRequest
    arguments:
      - '@service.logger'
      - '@service.database'
```

**Elements:**
- `id` - Service identifier for dependency injection - MANDATORY
- `class` - Fully qualified class name - MANDATORY
- `arguments` - Constructor dependencies
  - `@service.name` - Reference to another service
  - Scalar values - Direct arguments

### Factory Services

```yaml
container:
  - id: service.http_client
    factory: App\Factories\HttpClientFactory
    method: create
    arguments:
      - timeout: 30
```

---

## 🔨 YAML HELPERS

### !php Helper

**Execute PHP code:**

```yaml
guard: !php "return fn($t) => $t->ready;"
handler: !php "return fn($t) => error_log($t->message);"
```

### !env Helper

**Access environment variables:**

```yaml
container:
  - id: service.api
    class: App\ApiClient
    arguments:
      - apiKey: !env API_KEY
      - baseUrl: !env API_BASE_URL
```

### !service Helper

**Reference container services:**

```yaml
context:
  logger: !service service.logger
  config: !service service.config
```

---

## 💻 REGIONBUILDER PATTERNS

### Basic Construction

```php
use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\TransitionsFeature;

$region = (new RegionBuilder())
    ->enableFeatures(new TransitionsFeature())
    ->setStates('idle', 'processing', 'done')
    ->markInitial('idle')
    ->markFinal('done')
    ->build();
```

### With Actions

```php
$region = (new RegionBuilder())
    ->setStates('idle', 'active')
    ->markInitial('idle')
    ->on('idle', 'start', function($trigger) {
        echo "Starting...\n";
    })
    ->onEnter('active', function($trigger) {
        echo "Entered active state\n";
    })
    ->build();
```

### With Transitions

```php
use Noem\State\Feature\Transitions\AddTransition;

$region = (new RegionBuilder())
    ->enableFeatures(new TransitionsFeature())
    ->setStates('A', 'B', 'C')
    ->markInitial('A')
    ->addBuildStep(new AddTransition('A', 'B', fn($t): bool => $t->ready))
    ->addBuildStep(new AddTransition('B', 'C', fn($t): bool => $t->done))
    ->build();
```

### With Extended State

```php
use Noem\State\Feature\ExtendedState\ExtendedState;

$region = (new RegionBuilder())
    ->enableFeatures(new ExtendedState())
    ->setStates('counting')
    ->markInitial('counting')
    ->on('counting', 'increment', function($trigger, $context) {
        $context['counter']++;
    })
    ->build(['loader' => ['array' => [
        'context' => ['counter' => 0]
    ]]]);
```

### Loading from YAML

```php
use Noem\State\Feature\Loader\RegionLoader;

$loader = new RegionLoader();
$builder = $loader->fromYaml(file_get_contents('machine.yaml'));
$region = $builder->build();
```

### Self-Contained (Holon)

```php
use Noem\State\Feature\Loader\SelfContainedLoader as Holon;

// Single-line bootstrap
$region = Holon::fromYaml('machine.yaml');

// With auto-run
$result = Holon::fromYaml('machine.yaml', [
    'autoRun' => true,
    'maxIterations' => 1000,
]);
```

---

## 🚀 MACHINE EXECUTION PATTERNS

### Manual Event Loop

```php
$region = buildRegion();

while (!$region->isFinal()) {
    $event = getNextEvent();  // From queue, socket, stdin, etc.
    $region->trigger($event);
}
```

### Async Event Loop

```php
use Noem\State\Feature\Async\AsyncFeature;

$region = (new RegionBuilder())
    ->enableFeatures(new ExtendedState(), new AsyncFeature())
    ->setStates('idle', 'waiting', 'done')
    ->markInitial('idle')
    ->build(['loader' => ['array' => [
        'context' => [
            'resolvers' => [
                'data' => ['handler' => 'service.fetcher']
            ]
        ]
    ]]]);

// Tick-based execution
while (!$region->isFinal()) {
    $region->tick();  // Process one async task
    usleep(1000);     // Yield to system
}
```

### Event-Driven (Callback-based)

```php
$region = (new RegionBuilder())
    ->setStates('listening')
    ->markInitial('listening')
    ->on('listening', 'request', function($trigger) use ($region) {
        handleRequest($trigger);
        $region->trigger($nextEvent);  // Chain next event
    })
    ->build();

// Trigger from external source
$socket->on('data', fn($data) => $region->trigger((object)['type' => 'request', 'data' => $data]));
```

---

## 📚 EXAMPLE MACHINES

### Simple Counter

```yaml
name: counter
states:
  - name: counting
    on:
      increment:
        - handler: action.increment
      decrement:
        - handler: action.decrement

initial: counting

context:
  counter: 0

container:
  - id: action.increment
    class: !php "return fn($t, $ctx) => $ctx['counter']++;"

  - id: action.decrement
    class: !php "return fn($t, $ctx) => $ctx['counter']--;"
```

### HTTP Server

```yaml
name: webserver
states:
  - name: listening
    on:
      connection:
        - handler: action.accept_connection

  - name: processing
    on:
      request_parsed:
        - handler: action.route_request
      response_ready:
        - handler: action.send_response

  - name: closing
    onEnter:
      - handler: action.close_connection

transitions:
  - from: listening
    to: processing
    guard: !php "return fn($t) => $t->type === 'connection';"

  - from: processing
    to: listening
    guard: !php "return fn($t) => $t->type === 'response_sent';"

  - from: processing
    to: closing
    guard: !php "return fn($t) => isset($t->error);"

initial: listening
final:
  - closing

container:
  - id: action.accept_connection
    class: App\Actions\AcceptConnection
  - id: action.route_request
    class: App\Actions\RouteRequest
  - id: action.send_response
    class: App\Actions\SendResponse
  - id: action.close_connection
    class: App\Actions\CloseConnection
```

---

## 🚨 COMMON PATTERNS

### State Check Guards

```yaml
transitions:
  - from: loading
    to: ready
    guard: !php "return fn($t) => $t->loaded && !isset($t->error);"
```

### Timeout Handling

```yaml
states:
  - name: waiting
    onEnter:
      - handler: action.start_timer

transitions:
  - from: waiting
    to: timeout
    guard: !php "return fn($t) => $t->type === 'timeout';"
```

### Error Recovery

```yaml
states:
  - name: processing
  - name: error
    onEnter:
      - handler: action.log_error
      - handler: action.attempt_recovery
  - name: failed

transitions:
  - from: processing
    to: error
    guard: !php "return fn($t) => isset($t->error);"

  - from: error
    to: processing
    guard: !php "return fn($t) => $t->recovered;"

  - from: error
    to: failed
    guard: !php "return fn($t) => $t->retries >= 3;"

final:
  - failed
```

---

## 🐛 DEBUGGING PROTOCOL

### Enable Logging

```php
$region->onEnter('*', function($trigger) {
    error_log("Entering state: " . $this->currentState);
});

$region->on('*', '*', function($trigger) {
    error_log("Received event: " . json_encode($trigger));
});
```

### State Inspection

```php
echo "Current state: " . $region->currentState . "\n";
echo "Is final: " . ($region->isFinal() ? 'yes' : 'no') . "\n";
echo "Is in 'processing': " . ($region->isInState('processing') ? 'yes' : 'no') . "\n";
```

### Transition Debugging

```php
// Add logging to guards
guard: !php "return function($t) {
    $result = $t->ready;
    error_log('Guard evaluated: ' . ($result ? 'true' : 'false'));
    return $result;
};"
```

### Context Inspection

```php
$context = $region->getContext();
print_r($context);
```

---

## 🧪 TESTING MACHINES PROTOCOL

### Using Machine Test Base Classes

```php
use Noem\State\Tests\PHPUnit\E2E\ApplicationTestCase;

class MyMachineTest extends ApplicationTestCase
{
    protected function yaml(): string
    {
        return file_get_contents(__DIR__ . '/../../../machines/my-machine/machine.yml');
    }

    protected function container(): iterable
    {
        return [
            'service.name' => fn() => $this->mockService,
            'action.handler' => function ($trigger) {
                $this->recordAction('handler', $trigger);
            },
        ];
    }

    public function testMachineBehavior(): void
    {
        $region = $this->region();
        $region->trigger((object)['type' => 'test']);

        $this->assertTrue($region->isInState('expected_state'));
    }
}
```

### Mocking External Dependencies

```php
use Noem\State\Tests\PHPUnit\E2E\NetworkMachineTestCase;

class WebServerTest extends NetworkMachineTestCase
{
    public function testHandlesRequest(): void
    {
        $region = $this->region();

        // Queue mock request
        $connection = $this->queueHttpRequest('GET', '/test');

        // Execute machine
        $this->tickN($region, 10);

        // Verify behavior
        $this->assertConnectionClosed($connection);
    }

    protected function container(): iterable
    {
        return [
            'socket' => fn() => $this->mockSocket,
        ];
    }
}
```

---

## 🔍 FEATURE-SPECIFIC CONTEXT PROTOCOL

**EXECUTE when working on machines:**

```
STEP 1: Identify machine location
  └─ machines/{machine-name}/

STEP 2: Check for feature-specific docs
  └─ CHECK: machines/{machine-name}/CLAUDE.md

STEP 3: Load if exists
  ├─ EXISTS → Load and follow instructions
  └─ MISSING → Proceed with general guidance
```

---

## 🚨 VIOLATION PROTOCOLS

### Violation: Guard Missing Trigger Parameter

**WRONG:**
```yaml
guard: !php "return fn() => true;"
```

**CORRECT:**
```yaml
guard: !php "return fn($t) => true;"
```

### Violation: Handler Not in Container

**WRONG:**
```yaml
on:
  event:
    - handler: action.missing  # Not defined in container
```

**CORRECT:**
```yaml
container:
  - id: action.missing
    class: App\Actions\Missing

states:
  - name: state
    on:
      event:
        - handler: action.missing  # Now defined
```

### Violation: Circular Dependencies

**WRONG:**
```yaml
container:
  - id: service.a
    class: ServiceA
    arguments: ['@service.b']

  - id: service.b
    class: ServiceB
    arguments: ['@service.a']  # Circular!
```

**CORRECT:** Refactor to remove circular dependency.

### Violation: Wrong Feature Order

**WRONG:**
```yaml
features:
  - Noem\State\Feature\Async\AsyncFeature
  - Noem\State\Feature\ExtendedState\ExtendedState
```

**CORRECT:**
```yaml
features:
  - Noem\State\Feature\ExtendedState\ExtendedState  # First
  - Noem\State\Feature\Async\AsyncFeature           # Second
```

### Violation: Missing Initial State

**WRONG:**
```yaml
states:
  - name: idle
  - name: active
# No initial state defined
```

**CORRECT:**
```yaml
states:
  - name: idle
  - name: active
initial: idle  # MANDATORY
```

---

## 📂 MACHINE DIRECTORY STRUCTURE

```
machines/
└── machine-name/
    ├── machine.yml        # State machine definition
    ├── machine.php        # Entry point / bootstrap
    ├── src/               # Custom action handlers
    │   ├── Actions/
    │   ├── Services/
    │   └── container.php  # Container configuration
    └── CLAUDE.md          # Feature-specific docs (optional)
```

---

## 📚 PROJECT EXAMPLES

**Example machines in project:**
- `machines/webserver/` - HTTP server state machine
- `machines/middleware-test-runner/` - Spec test executor

**Related documentation:**
- `specs/machines/` - Machine specifications
- `tests/PHPUnit/E2E/` - Machine tests
- `src/Feature/Loader/` - YAML loader implementation

---

## 🔗 SKILL INTEGRATION

**Load with these skills:**
- **specification** - Machine specs
- **testing** - E2E testing
- **core-development** - Feature usage
- **documentation** - Machine docs
