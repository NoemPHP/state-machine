# Machine Generator - Headless Machine Generation Holon

## Overview

The `machine-generator` is a **headless** state machine that generates Holon machines from natural language descriptions. It has no CLI/UI dependencies and communicates purely via structured messages, making it consumable as a tool by any interface (CLI, web, API, etc.).

## Key Characteristics

- **Headless**: No STDIN/STDOUT - purely message-driven
- **Tool-Consumable**: Designed to be invoked via AbilitiesFeature
- **Event-Driven**: Emits progress messages at each stage
- **Stateful**: Maintains generation context across async operations
- **Composable**: Can be spawned as child runtime via OrthogonalRegions

## Architecture

### Message Protocol

See [MESSAGE_PROTOCOL.md](./MESSAGE_PROTOCOL.md) for complete message format documentation.

**Key Message Types**:
- `generate.request` - Initiate generation
- `analysis.complete` - Requirements analysis results
- `question.request` - Request clarification from user
- `question.response` - User's answer to question
- `progress.update` - Generation progress
- `validation.result` - Validation outcome
- `generation.complete` - Success
- `generation.failed` - Failure

### State Flow

```
idle → [generate.request] →
analyzing_requirements → [emit: analysis.complete] →
questioning → [emit: question.request, wait for question.response] →
planning_machine → [emit: progress.update] →
generating_yaml → [emit: progress.update] →
generating_functions → [emit: progress.update] →
validating_output → [emit: validation.result] →
delivering_machine → [emit: generation.complete] →
finished
```

## Usage

### As a Standalone Tool (Direct Invocation)

```php
use Noem\State\Feature\Loader\Holon;

// Load generator
$generator = Holon::fromYaml('/path/to/machine-generator/holon.yml');

// Subscribe to messages
$generator->subscribe('analysis.complete', function($msg) {
    echo "Confidence: " . ($msg->payload['confidence'] * 100) . "%\n";
});

$generator->subscribe('question.request', function($msg) {
    echo "Q: " . $msg->payload['question'] . "\n";
    // Send answer via question.response event
});

$generator->subscribe('generation.complete', function($msg) {
    echo "Generated: " . $msg->payload['machine_name'] . "\n";
});

// Trigger generation
$generator->dispatch('generate.request', (object) [
    'correlation_id' => uniqid('gen-'),
    'timestamp' => time(),
    'payload' => [
        'description' => 'Create a todo list app',
        'qa_history' => [],
    ],
]);

// Run until complete
while (!$generator->isComplete()) {
    $generator->run();
}
```

### As an Ability (Recommended Pattern)

```yaml
# In consumer holon
abilities:
  - name: generate_machine
    description: "Generate a Holon state machine"
    parameters:
      type: object
      properties:
        description:
          type: string
    handler: !php |
      return function(object $t): \Generator {
          // Load and spawn generator
          $generatorBuilder = \Noem\State\Feature\Loader\Holon::fromYaml(
              machines_path('machine-generator', 'holon.yml')
          );
          $generator = $this->summon($generatorBuilder);
          yield;

          // Send request
          $generator->dispatch('generate.request', (object) [
              'payload' => ['description' => $t->parameters['description']],
          ]);
          yield;

          // Run generator
          while (!$generator->isComplete()) {
              $generator->run();
              yield;
          }

          return ['success' => true];
      };
```

Then invoke from consumer:

```php
$message = $this->abilities('generate_machine', [
    'description' => 'Create a todo list app',
]);
```

## Features

### AI-Powered Analysis

- Assesses confidence in understanding requirements (0.0-1.0)
- Identifies complexity level (simple/moderate/complex)
- Detects missing or unclear information
- Extracts structured requirements

### Interactive Clarification

- Asks focused questions when confidence < 75%
- Supports multi-round Q&A refinement
- Accumulates context from previous answers

### Smart Generation

- **Inline functions** for simple machines (≤4 states)
- **Separate file** for complex machines (>4 states)
- Validates YAML and PHP syntax
- Automatic retry with feedback (max 3 attempts)

### Robust Validation

- YAML syntax checking
- PHP syntax checking (via `php -l`)
- Structural validation (states, initial, final)
- Detailed error reporting with line numbers

## Configuration

### AI Backend

Edit `holon.yml` to configure Ollama or Anthropic:

```yaml
- class: Noem\State\Feature\Ai\AiConfigFeature
  config:
    credentials:
      ollama:
        baseUrl: 'http://localhost:11434/api'
    preferences:
      preferProviders: ['ollama']
```

### Retry Limits

```yaml
# In idle state onEnter
$this->set('max_retries', 3);  # Adjust retry limit
$this->set('confidence_threshold', 0.75);  # Adjust confidence threshold
```

## Files

| File | Purpose |
|------|---------|
| `holon.yml` | State machine definition (headless, message-driven) |
| `bootstrap.php` | Infrastructure (autoloader, helpers) |
| `holon-spec.yaml` | Holon format knowledge base (verbose, human-readable) |
| `src/AiHelper.php` | AI operation helpers |
| `src/TemplateHelper.php` | Template streaming helpers |
| `MESSAGE_PROTOCOL.md` | Message format specification |
| `README.md` | This file |

**Compact Spec (for AI agents)**: See `docs/holon-spec/` for machine-readable spec (~250 lines vs ~1200 lines):
- `schema.json` - JSON Schema for validation
- `example.yaml` - Complete annotated example
- `constraints.md` - Feature order, callback patterns, broadcast levels

## Dependencies

### Required Features

- `ExtendedState` - Context management
- `MessageFeature` - Event emission/handling
- `AsyncFeature` - Async AI operations
- `TemplateFeature` - Template streaming
- `AiFeature` - AI completions
- `AiConfigFeature` - Backend configuration

### Optional Features

None - designed to be minimal and composable

## Message Handling Examples

### Handle Analysis Results

```php
$generator->subscribe('analysis.complete', function($msg) {
    if ($msg->payload['needs_clarification']) {
        echo "Confidence too low, expect questions...\n";
    }
});
```

### Handle Questions

```php
$generator->subscribe('question.request', function($msg) use ($generator) {
    $question = $msg->payload['question'];
    echo "Q: {$question}\n> ";
    $answer = trim(fgets(STDIN));

    // Send answer back
    $generator->dispatch('question.response', (object) [
        'correlation_id' => $msg->correlation_id,
        'payload' => [
            'answer' => $answer,
            'cancel' => strtolower($answer) === 'exit',
        ],
    ]);
});
```

### Handle Progress

```php
$generator->subscribe('progress.update', function($msg) {
    $stage = $msg->payload['stage'];
    $message = $msg->payload['message'];
    $percent = $msg->payload['percent'] ?? 0;
    echo "[{$percent}%] {$message}\n";
});
```

### Handle Completion

```php
$generator->subscribe('generation.complete', function($msg) {
    $name = $msg->payload['machine_name'];
    $files = $msg->payload['files'];
    echo "Success! Generated {$name} with " . count($files) . " files\n";
});

$generator->subscribe('generation.failed', function($msg) {
    $reason = $msg->payload['reason'];
    $errors = $msg->payload['errors'];
    echo "Failed: {$reason}\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
});
```

## Design Principles

1. **Separation of Concerns**: Generation logic isolated from UI
2. **Message-Driven**: All communication via structured events
3. **Tool Composability**: Consumable by any interface
4. **Async-First**: Non-blocking I/O and AI operations
5. **Stateful**: Maintains context across async boundaries
6. **Testable**: Pure business logic, no I/O dependencies

## Limitations

- Single generation request at a time (no concurrent generations)
- Question/answer must complete before next stage
- No intermediate state persistence (in-memory only)
- No streaming of generated files (atomic writes only)

## Future Enhancements

1. **Streaming Generation**: Stream YAML/PHP as it's generated
2. **Persistent State**: Save/restore generation state
3. **Concurrent Generations**: Handle multiple requests in parallel
4. **Template Customization**: User-provided templates
5. **Validation Plugins**: Extensible validation pipeline
6. **Generation Metrics**: Timing, token usage, cost tracking

## License

Part of the Noem State Machine project.
