# Machine Generator Architecture - Separation of Concerns

## Overview

This document describes the architectural separation of the original `machine-agent` into two distinct, composable holons:

1. **machine-generator** - Headless business logic for machine generation
2. **cli-agent** - Interactive CLI interface that consumes machine-generator as a tool

This architecture demonstrates advanced Noem patterns: runtime composition, ability-based tool interfaces, and message-driven communication.

## Why Separate?

### Original Problem

The original `machine-agent` tightly coupled:
- **Business Logic**: AI analysis, planning, generation, validation
- **User Interface**: STDIN/STDOUT, prompts, progress display
- **Control Flow**: Synchronous, blocking on user input

This coupling made it difficult to:
- Reuse generation logic in different contexts (web, API, tests)
- Test business logic independently of I/O
- Compose machines into larger workflows
- Support multiple UIs (CLI, web, chat, etc.)

### Solution: Headless + Consumer Pattern

```
┌──────────────────────────────────────┐
│   Original: machine-agent           │
│   ├─ CLI I/O (echo, fgets)          │
│   ├─ AI Generation Logic            │
│   └─ State Management                │
└──────────────────────────────────────┘
                  │
                  │ Separated into
                  ▼
┌──────────────────────────────────────┐
│   machine-generator (Headless)       │
│   ├─ AI Generation Logic             │
│   ├─ Message Emission                │
│   └─ No I/O Dependencies             │
└──────────────────────────────────────┘
                  ▲
                  │ consumes via Abilities
                  │
┌──────────────────────────────────────┐
│   cli-agent (UI)                     │
│   ├─ CLI I/O (echo, fgets)           │
│   ├─ Message Display                 │
│   └─ Runtime Spawning                │
└──────────────────────────────────────┘
```

## Architecture Details

### machine-generator (Headless Core)

**Location**: `machines/machine-generator/`

**Responsibilities**:
- AI-powered requirement analysis
- Implementation planning
- YAML/PHP code generation
- Validation
- File delivery

**Interface**:
- **Input**: Messages (generate.request, question.response)
- **Output**: Messages (analysis.complete, question.request, progress.update, etc.)
- **No I/O**: Pure message-driven, no STDIN/STDOUT

**Key Features**:
- Stateful generation process
- Async AI operations
- Retry logic with limits
- Schema-based validation
- Message correlation for tracking

### cli-agent (Interactive UI)

**Location**: `machines/cli-agent/`

**Responsibilities**:
- Display welcome banner
- Collect user input via STDIN
- Invoke machine-generator as tool
- Display progress updates
- Handle interactive Q&A
- Show final results

**Interface**:
- **Input**: STDIN (user text)
- **Output**: STDOUT (prompts, progress, results)
- **Tool**: machine-generator (spawned as child runtime)

**Key Features**:
- Ability-based tool invocation
- Message subscription and display
- Interactive question/answer flow
- Progress visualization

## Message Protocol

### Format

All messages follow a consistent structure:

```php
[
    'type' => string,           // e.g., 'analysis.complete'
    'correlation_id' => string, // Tracks request-response pairs
    'timestamp' => int,         // Unix timestamp
    'payload' => array,         // Type-specific data
]
```

### Message Types

See `machines/machine-generator/MESSAGE_PROTOCOL.md` for complete specification.

**Key Types**:
- `generate.request` - Start generation
- `analysis.complete` - Analysis results
- `question.request` - Ask user for clarification
- `question.response` - User's answer
- `progress.update` - Generation progress
- `validation.result` - Validation outcome
- `generation.complete` - Success
- `generation.failed` - Failure

### Example Flow

```
CLI → Generator: generate.request
    payload: {description: "Create a todo list"}

Generator → CLI: analysis.complete
    payload: {confidence: 0.65, needs_clarification: true}

Generator → CLI: question.request
    payload: {question: "Should it support categories?"}

CLI → Generator: question.response
    payload: {answer: "Yes, with nesting"}

Generator → CLI: analysis.complete
    payload: {confidence: 0.85, needs_clarification: false}

Generator → CLI: progress.update
    payload: {stage: "planning", percent: 30}

Generator → CLI: progress.update
    payload: {stage: "generating_yaml", percent: 50}

Generator → CLI: generation.complete
    payload: {machine_name: "todo-list", files: {...}}
```

## Implementation Patterns

### 1. Runtime Spawning (Orthogonal Regions)

```php
// CLI agent spawns generator as child runtime
$generatorBuilder = Holon::fromYaml(machines_path('machine-generator', 'holon.yml'));
$generator = $this->summon($generatorBuilder);

// Parent continues while child runs
while (!$generator->isComplete()) {
    $generator->run();
    yield;
}
```

### 2. Ability-Based Tool Interface

```yaml
# Expose generator as callable ability
abilities:
  - name: generate_machine
    handler: !php |
      return function(object $t): \Generator {
          $generator = $this->summon($generatorBuilder);
          # ... send messages, collect results ...
          return $result;
      };
```

```php
// Invoke from consumer
$message = $this->abilities('generate_machine', [
    'description' => 'Create a todo list',
]);
```

### 3. Message-Driven Communication

```php
// Generator emits messages
$this->emit('progress.update', [
    'correlation_id' => $correlationId,
    'payload' => ['stage' => 'planning', 'percent' => 30],
]);

// CLI subscribes to messages
$generator->subscribe('progress.update', function($msg) {
    echo "[" . $msg->payload['percent'] . "%] " . $msg->payload['message'] . "\n";
});
```

### 4. Correlation Tracking

```php
// Request
$correlationId = uniqid('gen-');
$generator->dispatch('generate.request', (object) [
    'correlation_id' => $correlationId,
    'payload' => [...],
]);

// Response (matched by correlation_id)
$generator->subscribe('generation.complete', function($msg) use ($correlationId) {
    if ($msg->correlation_id === $correlationId) {
        // Handle this specific request's completion
    }
});
```

## Benefits of This Architecture

### 1. **Separation of Concerns**
- Business logic isolated from UI
- Generator can be tested without I/O
- UI can be replaced without changing generator

### 2. **Reusability**
- Generator usable by: CLI, web UI, API, tests, scripts
- CLI patterns applicable to other tools
- Message protocol standardized

### 3. **Composability**
- Generators can be chained (output of one → input of another)
- Multiple generators can run in parallel
- Tools can be nested arbitrarily

### 4. **Testability**
- Generator tested via message injection
- CLI tested with mocked generator
- Integration tests verify message flow

### 5. **Extensibility**
- Add new UIs without touching generator
- Add new message types without breaking compatibility
- Plugin architecture via message subscriptions

## Alternative Consumers

The headless `machine-generator` can be consumed by various interfaces:

### Web UI

```php
// HTTP endpoint
Route::post('/api/generate', function(Request $request) {
    $generator = Holon::fromYaml('machine-generator/holon.yml');

    // Subscribe to messages and push via SSE
    $generator->subscribe('*', function($msg) {
        Server::send()->event($msg->type)->data($msg->payload);
    });

    // Trigger generation
    $generator->dispatch('generate.request', (object) [
        'payload' => ['description' => $request->input('description')],
    ]);

    // Run until complete
    while (!$generator->isComplete()) {
        $generator->run();
    }
});
```

### REST API

```php
// Async job
Route::post('/api/machines', function(Request $request) {
    $jobId = dispatch(new GenerateMachineJob(
        $request->input('description')
    ));

    return response()->json(['job_id' => $jobId]);
});

// Job handler
class GenerateMachineJob {
    public function handle() {
        $generator = Holon::fromYaml('machine-generator/holon.yml');

        // Store messages in Redis
        $generator->subscribe('*', function($msg) {
            Redis::rpush("job:{$this->jobId}:messages", json_encode($msg));
        });

        // Run generation
        $generator->dispatch('generate.request', ...);
        while (!$generator->isComplete()) {
            $generator->run();
        }
    }
}
```

### Chat Bot

```php
// Discord/Slack bot
$bot->onMessage(function($message) use ($bot) {
    if (str_starts_with($message->content, '!generate ')) {
        $description = substr($message->content, 10);

        $generator = Holon::fromYaml('machine-generator/holon.yml');

        // Display messages in chat
        $generator->subscribe('question.request', function($msg) use ($bot, $message) {
            $bot->reply($message->channel, $msg->payload['question']);
        });

        $generator->subscribe('generation.complete', function($msg) use ($bot, $message) {
            $bot->reply($message->channel, "Generated: " . $msg->payload['machine_name']);
        });

        // Trigger
        $generator->dispatch('generate.request', ...);
        while (!$generator->isComplete()) {
            $generator->run();
        }
    }
});
```

### Automated Testing

```php
// Test generator behavior
class GeneratorTest extends TestCase {
    public function test_generates_simple_machine() {
        $generator = Holon::fromYaml('machine-generator/holon.yml');

        // Capture all messages
        $messages = [];
        $generator->subscribe('*', function($msg) use (&$messages) {
            $messages[] = $msg;
        });

        // Trigger
        $generator->dispatch('generate.request', (object) [
            'payload' => ['description' => 'Create a counter'],
        ]);

        // Run
        while (!$generator->isComplete()) {
            $generator->run();
        }

        // Assert messages
        $this->assertContains('analysis.complete', array_column($messages, 'type'));
        $this->assertContains('generation.complete', array_column($messages, 'type'));

        // Verify file created
        $this->assertFileExists('machines/counter/holon.yml');
    }
}
```

## Comparison: Before vs After

### Before (Monolithic)

```php
// machine-agent/holon.yml
states:
  - name: gathering_requirements
    action:
      - run: !php |
          return function(object $t): \Generator {
              echo "Describe your machine: ";  // ← Coupled to CLI
              $input = trim(fgets(STDIN));      // ← Coupled to CLI
              $this->set('user_request', $input);
              yield;
          };
```

**Issues**:
- Can't reuse without CLI
- Can't test without mocking I/O
- Can't replace UI easily

### After (Separated)

```php
// machine-generator/holon.yml (headless)
states:
  - name: analyzing_requirements
    action:
      - run: !php |
          return function(object $t): \Generator {
              $ai = new \MachineAgent\AiHelper($this);
              $analysis = $ai->analyzeRequirements(...);
              yield;

              $this->emit('analysis.complete', [  // ← Message emission
                  'payload' => [...],
              ]);
          };
```

```php
// cli-agent/holon.yml (UI)
states:
  - name: gathering_input
    action:
      - run: !php |
          return function(object $t): \Generator {
              echo "Describe your machine: ";   // ← UI logic
              $input = trim(fgets(STDIN));        // ← UI logic
              $this->set('user_description', $input);
              yield;
          };

  - name: invoking_generator
    action:
      - run: !php |
          return function(object $t): \Generator {
              $generator = $this->summon(...);   // ← Spawn generator
              $generator->dispatch('generate.request', ...);  // ← Send message
              yield;
          };
```

**Benefits**:
- Generator reusable without CLI
- Easy to test both independently
- UI replaceable without changing generator
- Clear separation of concerns

## File Structure

```
machines/
├── machine-agent/              # Original (preserved)
│   ├── holon.yml
│   ├── bootstrap.php
│   ├── holon-spec.yaml
│   ├── src/
│   └── README.md
│
├── machine-generator/          # NEW - Headless core
│   ├── holon.yml               # Message-driven state machine
│   ├── bootstrap.php           # Infrastructure
│   ├── holon-spec.yaml         # Knowledge base
│   ├── src/                    # Helper classes
│   ├── MESSAGE_PROTOCOL.md     # Message specification
│   └── README.md               # Usage guide
│
├── cli-agent/                  # NEW - CLI interface
│   ├── holon.yml               # UI state machine
│   └── README.md               # Usage guide
│
└── ARCHITECTURE_OVERVIEW.md    # This file
```

## Implementation Status

✅ **Complete** - All core functionality implemented:
- Headless machine-generator with message protocol
- CLI agent with full message subscriptions
- Interactive question/answer flow via STDIN
- Progress display and validation feedback
- Success/failure result handling

### Implementation Note

The current implementation uses **direct instantiation** rather than runtime spawning:

```php
// Generator loaded directly in ability handler
$generator = \Noem\State\Feature\Loader\Holon::fromYaml($generatorPath);

// Run to completion with message subscriptions
while (!$generator->isComplete()) {
    $generator->run();
    yield;
}
```

This approach still demonstrates:
- ✅ Tool composition (one holon consuming another)
- ✅ Message-driven architecture
- ✅ Ability-based interfaces
- ✅ Separation of concerns

**Future Enhancement**: When `OrthogonalRegionsFeature` is implemented, this can be upgraded to use `summon()` for true parallel execution.

## Next Steps

### Immediate Enhancements

1. **Test Suite**: Add integration tests for message flow
2. **Error Handling**: Enhanced error recovery in message passing
3. **Message History**: Persistent logging of message exchanges

### Future Extensions

1. **Web UI**: HTTP server consuming machine-generator
2. **API Gateway**: REST/GraphQL API wrapper
3. **Message Persistence**: Store message history
4. **Multi-Generator**: Orchestrate multiple generators
5. **Template Marketplace**: User-contributed generation templates

## Conclusion

The separation of `machine-agent` into `machine-generator` and `cli-agent` demonstrates:

- **Proper separation of concerns** (business logic vs UI)
- **Composable architecture** (tools as abilities)
- **Message-driven design** (loose coupling)
- **Reusability** (headless core, swappable UIs)
- **Testability** (mockable message interfaces)

This architecture pattern is applicable to any Noem machine that needs:
- Multiple UIs (CLI, web, API)
- Tool composition
- Independent testing
- Async message passing
- Runtime orchestration

The original `machine-agent` is preserved for reference and backwards compatibility.
