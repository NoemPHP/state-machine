# Region Development Skill - State Machine Application Development

## Purpose

This skill covers **state-machine-based application development** using the Regions library. It focuses on YAML schema, syntax, RegionBuilder usage patterns, and building complete machines.

## YAML Machine Schema

### Basic Structure

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

initial: initial_state_name
final:
  - final_state_name
```

### State Definition

```yaml
states:
  - name: idle
    on:
      start:  # Event name
        - handler: action.start_processing  # Service from container
    onEnter:
      - handler: action.log_entry
      - handler: action.initialize_resources
    onExit:
      - handler: action.cleanup
```

**Key Elements:**
- `name` - State identifier (string)
- `on` - Event → action mappings
- `onEnter` - Callbacks when entering state
- `onExit` - Callbacks when exiting state

### Event Handlers

Handlers reference services from the container:

```yaml
on:
  request_received:
    - handler: action.parse_request
    - handler: action.validate_input
    - handler: action.route_request
```

**Container Configuration:**
```yaml
container:
  - id: action.parse_request
    class: App\Actions\ParseRequest
    arguments:
      - '@service.logger'
```

### Transitions

Automatic transitions with guard conditions:

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

**Guard Requirements:**
- Must accept trigger parameter: `fn($t)`
- Must return boolean
- First matching guard wins

### Initial and Final States

```yaml
initial: idle  # State machine starts here

final:  # Machine stops when reaching any of these
  - done
  - error
  - cancelled
```

### Extended State (Context)

Shared data across states:

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

States can contain child regions:

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

Multiple regions executing simultaneously:

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

Dynamic child region creation:

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

**Spawn Modes:**
- `PERSISTENT` - Child survives parent state changes (default)
- `DYNAMIC` - Child destroyed when parent changes state

### Async Resolvers

For lazy-loaded data:

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

**Access in actions:**
```php
public function __invoke(object $trigger, array $context): void
{
    $user = $context['userData'];  // Lazy-loaded on first access
}
```

### Templates

Dynamic content generation:

```yaml
templates:
  greeting: "Hello, {{ name }}! Welcome to {{ app.name }}."
  error: "Error {{ code }}: {{ message }}"
```

**Usage:**
```php
$rendered = $templateEngine->render('greeting', [
    'name' => 'Alice',
    'app' => ['name' => 'MyApp']
]);
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

## Container Configuration

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

**Key Elements:**
- `id` - Service identifier for dependency injection
- `class` - Fully qualified class name
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

## YAML Helpers

### !php Helper

Execute PHP code:

```yaml
guard: !php "return fn($t) => $t->ready;"
handler: !php "return fn($t) => error_log($t->message);"
```

### !env Helper

Access environment variables:

```yaml
container:
  - id: service.api
    class: App\ApiClient
    arguments:
      - apiKey: !env API_KEY
      - baseUrl: !env API_BASE_URL
```

### !service Helper

Reference container services:

```yaml
context:
  logger: !service service.logger
  config: !service service.config
```

## RegionBuilder Patterns

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

## Machine Execution Patterns

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

## Example Machines

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

### Worker Pool

```yaml
name: worker_pool
states:
  - name: coordinating
    spawn:
      - guard: !php "return fn($t) => $t->type === 'spawn_worker';"
        region:
          states:
            - name: idle
              on:
                task:
                  - handler: action.process_task
            
            - name: busy
              on:
                complete:
                  - handler: action.mark_complete
          
          transitions:
            - from: idle
              to: busy
              guard: !php "return fn($t) => $t->type === 'task';"
            
            - from: busy
              to: idle
              guard: !php "return fn($t) => $t->type === 'complete';"
          
          initial: idle

initial: coordinating

container:
  - id: action.process_task
    class: App\Actions\ProcessTask
  - id: action.mark_complete
    class: App\Actions\MarkComplete
```

## Common Patterns

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

### Hierarchical Workflows

```yaml
states:
  - name: order_processing
    regions:
      - name: validation
        states:
          - name: checking_inventory
          - name: verifying_payment
          - name: confirmed
        initial: checking_inventory
        final: [confirmed]
      
      - name: fulfillment
        states:
          - name: pending
          - name: picking
          - name: packing
          - name: shipped
        initial: pending
        final: [shipped]
```

## Debugging Machines

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

## Testing Machines

### Using Machine Test Base Classes

```php
use Noem\State\Tests\PHPUnit\E2E\ApplicationTestCase;

class MyMachineTest extends ApplicationTestCase
{
    protected function yaml(): string
    {
        return file_get_contents(__DIR__ . '/../machines/my-machine/machine.yml');
    }
    
    protected function container(): iterable
    {
        return [
            'action.handler' => fn($t) => $this->recordAction('handler', $t),
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

## Feature-Specific AGENTS.md Files

When working on machines in `machines/`, check for feature-specific documentation:

```
machines/
└── webserver/
    ├── machine.yml
    ├── machine.php
    ├── src/
    └── AGENTS.md          # ← Load if exists
```

**Loading pattern:**
```
IF working on machine in machines/{machine-name}/
THEN check for machines/{machine-name}/AGENTS.md
IF exists THEN load and follow those instructions
```

## Common Pitfalls

### Pitfall 1: Guard Missing Trigger Parameter

```yaml
# ❌ WRONG
guard: !php "return fn() => true;"

# ✅ CORRECT
guard: !php "return fn($t) => true;"
```

### Pitfall 2: Handler Not in Container

```yaml
# ❌ WRONG: Handler referenced but not defined
on:
  event:
    - handler: action.missing

# ✅ CORRECT: Handler defined in container
container:
  - id: action.missing
    class: App\Actions\Missing
```

### Pitfall 3: Circular Dependencies

```yaml
# ❌ WRONG: Service A depends on B, B depends on A
container:
  - id: service.a
    class: ServiceA
    arguments: ['@service.b']
  
  - id: service.b
    class: ServiceB
    arguments: ['@service.a']
```

### Pitfall 4: Wrong Feature Order

```yaml
# ❌ WRONG: AsyncFeature before ExtendedState
features:
  - Noem\State\Feature\Async\AsyncFeature
  - Noem\State\Feature\ExtendedState\ExtendedState

# ✅ CORRECT: ExtendedState first
features:
  - Noem\State\Feature\ExtendedState\ExtendedState
  - Noem\State\Feature\Async\AsyncFeature
```

### Pitfall 5: Forgetting Initial/Final States

```yaml
# ❌ WRONG: No initial state
states:
  - name: idle
  - name: active

# ✅ CORRECT: Initial state defined
states:
  - name: idle
  - name: active
initial: idle
```

## Integration with Other Skills

- **For specs** → Use specification skill
- **For tests** → Use testing skill
- **For core features** → Use core-development skill
- **For documentation** → Use documentation skill

## Reference

### Machine Directory Structure

```
machines/
└── machine-name/
    ├── machine.yml        # State machine definition
    ├── machine.php        # Entry point / bootstrap
    ├── src/               # Custom action handlers
    │   ├── Actions/
    │   ├── Services/
    │   └── container.php  # Container configuration
    └── AGENTS.md          # Feature-specific docs (optional)
```

### Example Machines in Project

- `machines/webserver/` - HTTP server state machine
- `machines/middleware-test-runner/` - Spec test executor

### Related Documentation

- `specs/machines/` - Machine specifications
- `tests/PHPUnit/E2E/` - Machine tests
- `src/Feature/Loader/` - YAML loader implementation

## When to Load This Skill

**Always load when:**
- Building state machine applications
- Writing YAML machine configs
- Working on machines in `machines/`
- Debugging machine execution
- Creating action handlers

**Combine with:**
- **specification** skill - for machine specs
- **testing** skill - for E2E testing
- **core-development** skill - for feature usage
- **documentation** skill - for machine docs
