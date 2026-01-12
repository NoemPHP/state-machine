# CLI Agent - Interactive Machine Generator Interface

## Overview

The `cli-agent` is an interactive command-line interface that consumes the `machine-generator` holon as a tool. It demonstrates advanced Noem patterns:
- **Runtime Composition**: Spawning holons as child runtimes
- **Ability-Based Tools**: Exposing holons as callable abilities
- **Message Passing**: Inter-holon communication via events
- **Separation of Concerns**: UI logic isolated from business logic

## Architecture

### Component Diagram

```
┌─────────────────────────────────────────────┐
│           CLI Agent (Parent)                │
│  ┌───────────────────────────────────────┐  │
│  │  States:                              │  │
│  │  - welcome                            │  │
│  │  - gathering_input                    │  │
│  │  - invoking_generator  ←──────┐      │  │
│  │  - finished                    │      │  │
│  └────────────────────────────────│──────┘  │
│                                   │          │
│  ┌────────────────────────────────▼──────┐  │
│  │  Ability: generate_machine           │  │
│  │  - Spawns machine-generator          │  │
│  │  - Pipes messages back/forth         │  │
│  │  - Returns result                    │  │
│  └──────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
                    │
                    │ spawns as child runtime
                    ▼
┌─────────────────────────────────────────────┐
│      Machine Generator (Child)              │
│  ┌───────────────────────────────────────┐  │
│  │  Headless - No CLI I/O                │  │
│  │  Emits messages:                      │  │
│  │  - analysis.complete                  │  │
│  │  - question.request                   │  │
│  │  - progress.update                    │  │
│  │  - validation.result                  │  │
│  │  - generation.complete/failed         │  │
│  └───────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
```

### Message Flow

```
CLI Agent                    Machine Generator
────────────────────────────────────────────────
[User Input]
    │
    ├─→ generate.request ─→
    │                          [Analyzing...]
    ←── analysis.complete ←─
    │
    │   Display confidence
    │
    ←── question.request ←─
    │                          [Needs info]
    │   Prompt user
    │
    ├─→ question.response ─→
    │                          [Planning...]
    ←── progress.update ←─
    │
    │   Show progress
    │
    ←── progress.update ←─
    │                          [Generating...]
    ←── progress.update ←─
    │                          [Validating...]
    ←── validation.result ←─
    │
    │   Show errors (if any)
    │
    ←── generation.complete ←─
    │
    │   Display success
    │
    [Exit]
```

## Key Features

### Direct Generator Instantiation

The CLI agent loads and runs the machine-generator directly within the ability handler:

```php
$generatorPath = machines_path('machine-generator', 'holon.yml');
$generator = \Noem\State\Feature\Loader\Holon::fromYaml($generatorPath);

// Run generator with message subscriptions
while (!$generator->isComplete()) {
    $generator->run();
    yield;
}
```

This demonstrates **tool consumption** where one holon uses another as a subordinate component.

### Ability-Based Tool Interface

The `generate_machine` ability encapsulates all generator interaction:

```yaml
abilities:
  - name: generate_machine
    description: "Generate a Holon state machine"
    handler: !php |
      return function(object $t): \Generator {
          # Spawn generator
          # Send messages
          # Collect results
          # Return response
      };
```

Consumers invoke it like any other ability:

```php
$message = $this->abilities('generate_machine', [
    'description' => 'Create a todo list',
]);
```

### Message-Driven Communication

All communication happens via structured messages:

```php
// CLI → Generator
$generator->dispatch('generate.request', (object) [
    'payload' => ['description' => $userInput],
]);

// Generator → CLI (subscriptions)
$generator->subscribe('progress.update', function($msg) {
    echo $msg->payload['message'] . "\n";
});
```

## Usage

### Quick Start

```bash
# Run CLI agent
ddev exec php run.php machines/cli-agent/holon.yml

# Follow prompts
Describe your machine: Create a todo list app
[Generation in progress...]
```

### Programmatic Usage

```php
use Noem\State\Feature\Loader\Holon;

// Load CLI agent
$cli = Holon::fromYaml('/path/to/cli-agent/holon.yml');

// Simulate user input (for testing)
$cli->set('user_description', 'Create a todo list');

// Run
while (!$cli->isComplete()) {
    $cli->run();
}
```

## State Descriptions

### welcome
**Purpose**: Display welcome banner and instructions

### gathering_input
**Purpose**: Collect user's machine description via STDIN
- Reads user input
- Handles 'exit' command
- Stores description in context

### invoking_generator
**Purpose**: Invoke generate_machine ability and handle messages
- Spawns machine-generator as child runtime
- Pipes messages between generator and CLI
- Displays progress updates to user
- Handles question/answer flow interactively

### finished
**Purpose**: Display final result and exit gracefully
- Shows success/failure message
- Displays generated machine info
- Clean exit

## Message Handling

The `generate_machine` ability subscribes to all generator messages and handles them appropriately:

### Analysis Results

```php
// Display confidence and clarification status
$generatorRuntime->subscribe('analysis.complete', function($msg) {
    $confidence = ($msg->payload['confidence'] ?? 0) * 100;
    echo sprintf("[Analysis] Confidence: %.0f%%", $confidence);
    if ($msg->payload['needs_clarification'] ?? false) {
        echo " - Clarification needed\n";
    } else {
        echo " - Proceeding with generation\n";
    }
});
```

### Interactive Questions

```php
// Handle clarifying questions with STDIN/STDOUT
$generatorRuntime->subscribe('question.request', function($msg) use ($generatorRuntime) {
    echo "\n[Question] " . ($msg->payload['question'] ?? '') . "\n";
    echo "> ";
    $answer = trim(fgets(STDIN));

    // Send answer back to generator
    $generatorRuntime->dispatch('question.response', (object) [
        'correlation_id' => $msg->correlation_id ?? '',
        'timestamp' => time(),
        'payload' => [
            'answer' => $answer,
            'cancel' => strtolower($answer) === 'exit',
        ],
    ]);
});
```

### Progress Updates

```php
// Display generation progress with percentage
$generatorRuntime->subscribe('progress.update', function($msg) {
    $message = $msg->payload['message'] ?? '';
    $percent = $msg->payload['percent'] ?? 0;
    echo sprintf("[%d%%] %s\n", $percent, $message);
});
```

### Validation Results

```php
// Show validation errors with retry information
$generatorRuntime->subscribe('validation.result', function($msg) {
    if (!($msg->payload['passed'] ?? false)) {
        echo "\n[Validation Failed]\n";
        foreach ($msg->payload['errors'] ?? [] as $error) {
            echo "  ✗ {$error}\n";
        }
        $retryCount = $msg->payload['retry_count'] ?? 0;
        $maxRetries = $msg->payload['max_retries'] ?? 3;
        echo "  Retry {$retryCount}/{$maxRetries}\n";
    } else {
        echo "[Validation] ✓ Passed\n";
    }
});
```

### Completion (Success)

```php
// Capture successful generation result
$generatorRuntime->subscribe('generation.complete', function($msg) use (&$completed, &$result) {
    $completed = true;
    $result = $msg->payload;
    // Contains: machine_name, directory, files, inline_functions
});
```

### Completion (Failure)

```php
// Capture failure information
$generatorRuntime->subscribe('generation.failed', function($msg) use (&$failed, &$result) {
    $failed = true;
    $result = $msg->payload;
    // Contains: reason, message, errors, suggestions
});
```

## Files

| File | Purpose |
|------|---------|
| `holon.yml` | CLI state machine definition with inline callbacks |
| `bootstrap.php` | Minimal infrastructure (path helpers) |
| `README.md` | This file |

## Dependencies

### Required Features

- `ExtendedState` - Context management
- `AsyncFeature` - Async I/O operations
- `MessageFeature` - Message passing
- `AbilitiesFeature` - Tool invocation

### Required Machines

- `machine-generator` - The headless generator consumed as a tool

## Design Patterns Demonstrated

### 1. **Separation of Concerns**
- CLI handles user interaction
- Generator handles business logic
- Clear interface boundary via messages

### 2. **Tool Composition**
- One holon consumes another as a tool
- Generator loaded and run within ability handler
- Encapsulated interaction via ability interface

### 3. **Message Passing**
- Structured message protocol
- Type-safe message format
- Correlation IDs for request/response tracking
- Event subscription for inter-holon communication

### 4. **Ability-Based Tools**
- Tools exposed as abilities
- Standard invocation interface
- Async response handling

### 5. **Interactive Flow Integration**
- STDIN/STDOUT in ability callbacks
- Synchronous question/answer within async execution
- Progress feedback during long-running operations

## Extension Points

### Custom Message Handlers

Add custom handling for any generator message:

```php
$generator->subscribe('analysis.complete', function($msg) {
    // Custom logic for analysis results
    $confidence = $msg->payload['confidence'];
    if ($confidence > 0.9) {
        echo "High confidence! This should be good.\n";
    }
});
```

### Alternative UIs

Replace CLI with:
- **Web UI**: HTTP server consuming generator via abilities
- **REST API**: API endpoints invoking generator
- **GUI**: Desktop app using same message protocol
- **Chat Bot**: Discord/Slack bot interface

### Middleware

Inject middleware into ability invocation:

```php
// Log all generator messages
$generator->subscribe('*', function($msg) {
    error_log("Generator message: " . $msg->type);
});
```

## Testing

### Unit Testing (Message Flow)

```php
// Mock message handling
$mockGenerator = new MockRuntime();
$mockGenerator->onDispatch('generate.request', function($msg) {
    // Simulate generator response
    $this->emit('analysis.complete', [
        'payload' => ['confidence' => 0.85],
    ]);
});

// Test CLI handles messages correctly
$cli->invoke('generate_machine', ['description' => 'test']);
assert($cli->get('analysis_received') === true);
```

### Integration Testing

```php
// Test full flow with real generator
$cli = Holon::fromYaml('cli-agent/holon.yml');
$cli->set('user_description', 'Create a counter');
$cli->run();

// Verify machine was generated
assert(file_exists('machines/counter/holon.yml'));
```

## Limitations

- Single-threaded (no concurrent generation requests)
- Terminal-only (no GUI)
- No persistent state (in-memory only)
- No message replay/history

## Future Enhancements

1. **Web UI**: HTTP server exposing generator via REST API
2. **Message Persistence**: Store message history for replay
3. **Multi-User**: Handle concurrent generation requests
4. **Progress Bar**: Visual progress indicator
5. **Auto-Retry**: Intelligent retry with error correction
6. **Template Selection**: Let user choose generation templates

## License

Part of the Noem State Machine project.
