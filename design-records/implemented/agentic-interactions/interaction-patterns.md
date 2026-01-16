# Agentic Interaction Patterns - Core Proposal

**Status**: Implemented
**Created**: 2026-01-03
**Related**: [README.md](./README.md)

## Overview

This proposal defines a **minimal set of standardized interaction patterns** for autonomous state machines to communicate with external agents, supervisors, and orchestration frameworks. These patterns complement the existing **Abilities API** (inward business logic invocation) by providing a **soft contract for outward interactions** (requesting information, decisions, and input from external entities).

**Core Principle**: Just as "abilities" enable external agents to invoke business logic on a machine, **interaction patterns** enable machines to request information and decisions from external agents.

---

## Problem Statement

### Current Situation

The state machine framework has robust **inward communication**:
- **Abilities API**: External agents invoke machine capabilities (`$this->abilities('processOrder', $params)`)
- **MessageFeature**: Internal request-response with correlation
- **SubscriptionFeature**: Event-based notifications

But lacks standardized **outward communication** patterns:
- ❌ No convention for machines to request user input
- ❌ No standard format for yes/no confirmations
- ❌ No protocol for presenting choices to external agents
- ❌ Each machine invents its own interaction patterns

### Real-World Scenario

**Machine-Agent** (autonomous machine generator) needs to:
1. Ask clarifying questions → **Prompt pattern**
2. Confirm user wants to proceed → **Confirm pattern**
3. Let user choose AI model → **Select pattern**
4. Let user pick multiple features → **Choice pattern**

Currently, each implementation is ad-hoc with no shared protocol.

### Requirements for Agentic Framework Integration

When machines run within orchestration frameworks (CLI, web UI, API), the framework needs to:
1. **Detect** when a machine needs external input
2. **Understand** what type of interaction is needed (confirm vs. select vs. prompt)
3. **Present** appropriate UI (buttons, dropdowns, text fields)
4. **Deliver** responses back to machine
5. **Track** interaction state (pending, answered, timeout)

**Without standardization**, each framework must custom-parse machine output or implement framework-specific protocols.

---

## Proposed Solution

### Core Interaction Patterns

Define **four fundamental interaction patterns** as specialized Message types:

| Pattern | Purpose | Response | Use Cases |
|---------|---------|----------|-----------|
| **Confirm** | Yes/No decision | `boolean` | Approve action, verify understanding |
| **Select** | Choose one option | `string` (selected key) | Pick from list, configuration choice |
| **Choice** | Choose multiple options | `array<string>` (selected keys) | Multi-select features, tags |
| **Prompt** | Free-form input | `string` (user input) | Gather text, ask questions |

### Design Principles

1. **Built on MessageFeature** - Leverage existing correlation and response handling
2. **JSON-Serializable** - Can persist, transmit, log interactions
3. **Framework-Agnostic** - Works in CLI, web UI, API, etc.
4. **Type-Safe** - Response validation via schemas
5. **Timeout-Aware** - Support asynchronous interaction with deadlines
6. **Extensible** - Can add new patterns (e.g., File Upload, Date Picker) later

---

## Architecture

### Message Hierarchy

```
Message (abstract)
  ├─ AbilityMessage        # Existing - inward invocation
  └─ InteractionRequest    # NEW - outward interaction (abstract)
       ├─ ConfirmRequest
       ├─ SelectRequest
       ├─ ChoiceRequest
       └─ PromptRequest

InteractionResponse (abstract)
  ├─ ConfirmResponse
  ├─ SelectResponse
  ├─ ChoiceResponse
  └─ PromptResponse
```

### Core Components

```php
// 1. Base Interaction Request
abstract class InteractionRequest extends Message
{
    public function __construct(
        public readonly string $question,      // Human-readable question
        public readonly ?string $context = null, // Optional additional context
        public readonly ?int $timeoutMs = null,  // Optional timeout in milliseconds
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }

    abstract public function getType(): string; // 'confirm', 'select', 'choice', 'prompt'
}

// 2. Base Interaction Response
abstract class InteractionResponse extends Message
{
    public function __construct(
        public readonly mixed $value,          // Type depends on interaction
        public readonly bool $cancelled = false, // User cancelled/timed out
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }
}
```

---

## Pattern Specifications

### 1. Confirm Pattern

**Use Case**: Yes/No decisions, approvals, verifications

```php
class ConfirmRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly bool $defaultValue = false, // Default if user hits Enter
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $correlationId);
    }

    public function getType(): string { return 'confirm'; }
}

class ConfirmResponse extends InteractionResponse
{
    public function __construct(
        public readonly bool $confirmed,
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($confirmed, $cancelled, $correlationId);
    }
}
```

**Example Usage**:

```php
// In state machine
->onAction('before_deploy', function(object $t): \Generator {
    $request = new ConfirmRequest(
        question: 'Deploy to production?',
        context: 'This will affect 10,000 users',
        defaultValue: false
    );

    $confirmed = yield from $this->interact($request);

    if ($confirmed) {
        // Proceed with deployment
    }
})
```

**JSON Format**:

```json
{
  "type": "Noem\\State\\Feature\\Interaction\\ConfirmRequest",
  "correlationId": "550e8400-e29b-41d4-a716-446655440000",
  "data": {
    "question": "Deploy to production?",
    "context": "This will affect 10,000 users",
    "defaultValue": false,
    "timeoutMs": null
  }
}
```

---

### 2. Select Pattern

**Use Case**: Choose one option from a list (dropdown, radio buttons)

```php
class SelectRequest extends InteractionRequest
{
    /**
     * @param array<string, SelectOption> $options Map of key => option
     */
    public function __construct(
        string $question,
        public readonly array $options,        // key => SelectOption
        public readonly ?string $defaultKey = null, // Pre-selected option
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $correlationId);
    }

    public function getType(): string { return 'select'; }
}

class SelectOption
{
    public function __construct(
        public readonly string $label,         // Display text
        public readonly ?string $description = null // Optional help text
    ) {}
}

class SelectResponse extends InteractionResponse
{
    public function __construct(
        public readonly ?string $selectedKey,  // null if cancelled
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($selectedKey, $cancelled, $correlationId);
    }
}
```

**Example Usage**:

```php
->onAction('choose_model', function(object $t): \Generator {
    $request = new SelectRequest(
        question: 'Which AI model should I use?',
        options: [
            'claude-sonnet' => new SelectOption(
                label: 'Claude Sonnet 4',
                description: 'High quality, expensive'
            ),
            'qwen-coder' => new SelectOption(
                label: 'Qwen Coder 7B',
                description: 'Good quality, local, fast'
            ),
            'llama3' => new SelectOption(
                label: 'Llama 3.2',
                description: 'Basic quality, very fast, free'
            ),
        ],
        defaultKey: 'qwen-coder'
    );

    $modelKey = yield from $this->interact($request);
    $this->set('selected_model', $modelKey);
})
```

**JSON Format**:

```json
{
  "type": "Noem\\State\\Feature\\Interaction\\SelectRequest",
  "correlationId": "550e8400-e29b-41d4-a716-446655440001",
  "data": {
    "question": "Which AI model should I use?",
    "options": {
      "claude-sonnet": {
        "label": "Claude Sonnet 4",
        "description": "High quality, expensive"
      },
      "qwen-coder": {
        "label": "Qwen Coder 7B",
        "description": "Good quality, local, fast"
      },
      "llama3": {
        "label": "Llama 3.2",
        "description": "Basic quality, very fast, free"
      }
    },
    "defaultKey": "qwen-coder",
    "timeoutMs": null
  }
}
```

---

### 3. Choice Pattern

**Use Case**: Choose multiple options from a list (checkboxes, multi-select)

```php
class ChoiceRequest extends InteractionRequest
{
    /**
     * @param array<string, ChoiceOption> $options Map of key => option
     * @param array<string> $defaultKeys Pre-selected options
     * @param int|null $minSelections Minimum required selections (null = 0)
     * @param int|null $maxSelections Maximum allowed selections (null = unlimited)
     */
    public function __construct(
        string $question,
        public readonly array $options,        // key => ChoiceOption
        public readonly array $defaultKeys = [],
        public readonly ?int $minSelections = null,
        public readonly ?int $maxSelections = null,
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $correlationId);
    }

    public function getType(): string { return 'choice'; }
}

class ChoiceOption
{
    public function __construct(
        public readonly string $label,
        public readonly ?string $description = null,
        public readonly bool $recommended = false  // Highlight as recommended
    ) {}
}

class ChoiceResponse extends InteractionResponse
{
    /**
     * @param array<string> $selectedKeys Empty array if cancelled
     */
    public function __construct(
        public readonly array $selectedKeys,
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($selectedKeys, $cancelled, $correlationId);
    }
}
```

**Example Usage**:

```php
->onAction('select_features', function(object $t): \Generator {
    $request = new ChoiceRequest(
        question: 'Which features should I enable?',
        options: [
            'async' => new ChoiceOption(
                label: 'Async Operations',
                description: 'Coroutine-based async/await',
                recommended: true
            ),
            'ai' => new ChoiceOption(
                label: 'AI Integration',
                description: 'LLM API access'
            ),
            'persistence' => new ChoiceOption(
                label: 'State Persistence',
                description: 'Save/restore machine state'
            ),
        ],
        minSelections: 1,
        maxSelections: null,
        defaultKeys: ['async']
    );

    $features = yield from $this->interact($request);
    $this->set('enabled_features', $features);
})
```

**JSON Format**:

```json
{
  "type": "Noem\\State\\Feature\\Interaction\\ChoiceRequest",
  "correlationId": "550e8400-e29b-41d4-a716-446655440002",
  "data": {
    "question": "Which features should I enable?",
    "options": {
      "async": {
        "label": "Async Operations",
        "description": "Coroutine-based async/await",
        "recommended": true
      },
      "ai": {
        "label": "AI Integration",
        "description": "LLM API access"
      },
      "persistence": {
        "label": "State Persistence",
        "description": "Save/restore machine state"
      }
    },
    "defaultKeys": ["async"],
    "minSelections": 1,
    "maxSelections": null
  }
}
```

---

### 4. Prompt Pattern

**Use Case**: Free-form text input, questions, custom input

```php
class PromptRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly ?string $placeholder = null,   // Placeholder text
        public readonly ?string $defaultValue = null,  // Pre-filled value
        public readonly ?string $validation = null,    // Regex pattern (optional)
        public readonly bool $multiline = false,       // Single vs. multi-line
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $correlationId);
    }

    public function getType(): string { return 'prompt'; }
}

class PromptResponse extends InteractionResponse
{
    public function __construct(
        public readonly ?string $input,  // null if cancelled
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($input, $cancelled, $correlationId);
    }
}
```

**Example Usage**:

```php
->onAction('gather_requirements', function(object $t): \Generator {
    $request = new PromptRequest(
        question: 'Describe the machine you want to create',
        placeholder: 'e.g., "A task executor that processes jobs from a queue"',
        multiline: true,
        validation: '^.{10,}$'  // At least 10 characters
    );

    $description = yield from $this->interact($request);
    $this->set('user_request', $description);
})
```

**JSON Format**:

```json
{
  "type": "Noem\\State\\Feature\\Interaction\\PromptRequest",
  "correlationId": "550e8400-e29b-41d4-a716-446655440003",
  "data": {
    "question": "Describe the machine you want to create",
    "placeholder": "e.g., \"A task executor that processes jobs from a queue\"",
    "defaultValue": null,
    "validation": "^.{10,}$",
    "multiline": true
  }
}
```

---

## InteractionFeature Implementation

### Feature Registration

```php
class InteractionFeature implements Feature
{
    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use($this->bindInteractMethod(...));
        $chainMail->use($this->installInteractionHandler(...));
    }

    /**
     * Bind $this->interact() to BoundAccess
     */
    private function bindInteractMethod(?BoundAccess $boundAccess = null): void
    {
        if ($boundAccess === null) {
            throw new \RuntimeException(
                'InteractionFeature requires ExtendedState'
            );
        }

        $boundAccess->link(function (BoundAccessParams $params, callable $next) {
            if ($params->type !== BoundAccessParams::TYPE_METHOD
                || $params->name !== 'interact') {
                return $next($params);
            }

            // Handle $this->interact(InteractionRequest)
            $request = $params->payload[0] ?? null;
            if (!$request instanceof InteractionRequest) {
                throw new \InvalidArgumentException(
                    'interact() requires InteractionRequest instance'
                );
            }

            // Dispatch interaction request and wait for response
            return $this->handleInteraction($params->region, $request);
        }, prepend: true);
    }

    /**
     * Handle interaction request-response cycle
     */
    private function handleInteraction(
        Region $region,
        InteractionRequest $request
    ): \Generator {
        $response = null;

        // Register response handler
        $request->then(function(InteractionResponse $r) use (&$response) {
            $response = $r;
        });

        // Emit interaction request to subscribers
        $region->notificationChain->call(new Notify($region, $request));

        // Wait for response
        while ($response === null) {
            yield;
        }

        // Return response value or throw if cancelled
        if ($response->cancelled) {
            throw new InteractionCancelledException(
                "Interaction cancelled or timed out"
            );
        }

        return $response->value;
    }
}
```

### Framework Integration Pattern

**Framework subscribes to interaction requests**:

```php
// In CLI framework
$region->on(function(InteractionRequest $request, ?Region $source = null) {
    match($request->getType()) {
        'confirm' => $this->handleConfirm($request),
        'select' => $this->handleSelect($request),
        'choice' => $this->handleChoice($request),
        'prompt' => $this->handlePrompt($request),
        default => throw new \RuntimeException('Unknown interaction type')
    };
});

private function handleConfirm(ConfirmRequest $request): void
{
    echo $request->question . " (y/n) [" . ($request->defaultValue ? 'Y' : 'n') . "]: ";
    $input = trim(fgets(STDIN));

    $confirmed = match(strtolower($input)) {
        'y', 'yes' => true,
        'n', 'no' => false,
        default => $request->defaultValue
    };

    $response = $request->createResponse(
        ConfirmResponse::class,
        ['confirmed' => $confirmed, 'cancelled' => false]
    );

    // Emit response back to machine
    $source->notificationChain->call(new Notify($source, $response));
}
```

---

## Integration with Existing Features

### Dependency Chain

```
InteractionFeature
  ├─ Requires: ExtendedState (for BoundAccess)
  ├─ Requires: SubscriptionFeature (for event emission)
  ├─ Requires: MessageFeature (for correlation)
  └─ Optional: AsyncFeature (for generator-based waiting)
```

**Load Order**:

```yaml
features:
  - class: Noem\State\Feature\ExtendedState\ExtendedState
  - class: Noem\State\Feature\Subscription\SubscriptionFeature
  - class: Noem\State\Feature\Message\MessageFeature
  - class: Noem\State\Feature\Interaction\InteractionFeature  # After dependencies
  - class: Noem\State\Feature\Async\AsyncFeature  # Optional
```

### MessageFeature Integration

- All InteractionRequest/Response messages extend `Message`
- Automatic correlation ID management
- Use `then()` for response handling
- Leverage existing subscription infrastructure

### BoundAccess Integration

- `$this->interact(InteractionRequest)` added to context helpers
- Returns Generator that yields until response received
- Throws `InteractionCancelledException` on timeout/cancel

---

## Use Cases

### 1. Machine-Agent Interactive Generation

```php
// State: questioning
->onAction('ask_clarification', function(object $t): \Generator {
    $questions = $this->get('remaining_questions');

    foreach ($questions as $q) {
        $answer = yield from $this->interact(new PromptRequest(
            question: $q['text'],
            context: 'This helps me generate a better machine',
            multiline: $q['long_form'] ?? false
        ));

        $this->appendTo('qa_history', [
            'question' => $q['text'],
            'answer' => $answer
        ]);
    }
})
```

### 2. Deployment Confirmation Workflow

```php
->onEnter('ready_to_deploy', function(object $t): \Generator {
    $changelog = $this->get('changes');

    $proceed = yield from $this->interact(new ConfirmRequest(
        question: 'Deploy these changes to production?',
        context: "Changes:\n" . implode("\n", $changelog),
        defaultValue: false,
        timeoutMs: 30000  // 30 second timeout
    ));

    if ($proceed) {
        $this->trigger('deploy');
    } else {
        $this->trigger('cancel');
    }
})
```

### 3. Multi-Feature Configuration

```php
->onAction('configure', function(object $t): \Generator {
    $features = yield from $this->interact(new ChoiceRequest(
        question: 'Select features to enable',
        options: [
            'cache' => new ChoiceOption('Caching', 'Redis-backed caching'),
            'auth' => new ChoiceOption('Authentication', 'JWT-based auth', true),
            'logging' => new ChoiceOption('Logging', 'Structured logging'),
            'metrics' => new ChoiceOption('Metrics', 'Prometheus metrics'),
        ],
        minSelections: 1,
        defaultKeys: ['auth']
    ));

    foreach ($features as $feature) {
        $this->summon("features/{$feature}/holon.yml");
    }
})
```

### 4. Dynamic Backend Selection

```php
->onAction('choose_storage', function(object $t): \Generator {
    $backend = yield from $this->interact(new SelectRequest(
        question: 'Choose storage backend',
        options: [
            'mysql' => new SelectOption('MySQL', 'Relational database'),
            'postgres' => new SelectOption('PostgreSQL', 'Advanced relational'),
            'mongodb' => new SelectOption('MongoDB', 'Document store'),
            'redis' => new SelectOption('Redis', 'Key-value cache'),
        ],
        defaultKey: 'postgres'
    ));

    $this->set('storage_backend', $backend);
})
```

---

## Framework Adapter Examples

### CLI Adapter

```php
class CLIInteractionAdapter
{
    public function __construct(private Region $region)
    {
        $region->on(fn(InteractionRequest $r, $s) => $this->handle($r, $s));
    }

    private function handle(InteractionRequest $request, Region $source): void
    {
        $response = match($request->getType()) {
            'confirm' => $this->renderConfirm($request),
            'select' => $this->renderSelect($request),
            'choice' => $this->renderChoice($request),
            'prompt' => $this->renderPrompt($request),
        };

        $source->notificationChain->call(new Notify($source, $response));
    }

    private function renderConfirm(ConfirmRequest $req): ConfirmResponse
    {
        // Use symfony/console QuestionHelper or similar
        $helper = new QuestionHelper();
        $question = new ConfirmationQuestion(
            $req->question . ' ',
            $req->defaultValue
        );

        $confirmed = $helper->ask($this->input, $this->output, $question);

        return $req->createResponse(ConfirmResponse::class, [
            'confirmed' => $confirmed,
            'cancelled' => false
        ]);
    }

    // Similar for select, choice, prompt...
}
```

### Web API Adapter

```php
class WebInteractionAdapter
{
    private array $pendingInteractions = [];

    public function __construct(private Region $region)
    {
        $region->on(fn(InteractionRequest $r, $s) => $this->enqueue($r));
    }

    /**
     * Enqueue interaction for API client
     */
    private function enqueue(InteractionRequest $request): void
    {
        $this->pendingInteractions[$request->correlationId()] = [
            'request' => $request,
            'timestamp' => time(),
            'status' => 'pending'
        ];
    }

    /**
     * API endpoint: GET /interactions/pending
     */
    public function getPending(): array
    {
        return array_values(array_filter(
            $this->pendingInteractions,
            fn($i) => $i['status'] === 'pending'
        ));
    }

    /**
     * API endpoint: POST /interactions/{correlationId}/respond
     */
    public function respond(string $correlationId, array $data): void
    {
        $interaction = $this->pendingInteractions[$correlationId] ?? null;
        if (!$interaction) {
            throw new \RuntimeException('Interaction not found');
        }

        $request = $interaction['request'];
        $responseClass = str_replace('Request', 'Response', get_class($request));

        $response = $request->createResponse($responseClass, $data);

        $this->region->notificationChain->call(
            new Notify($this->region, $response)
        );

        $this->pendingInteractions[$correlationId]['status'] = 'answered';
    }
}
```

---

## Extension Points

### Future Interaction Patterns

The architecture supports adding new patterns without breaking changes:

```php
// File upload
class FileUploadRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly array $allowedExtensions = [],
        public readonly int $maxSizeBytes = 10485760, // 10MB
        public readonly bool $multiple = false,
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $correlationId);
    }

    public function getType(): string { return 'file_upload'; }
}

class FileUploadResponse extends InteractionResponse
{
    public function __construct(
        public readonly array $files,  // [['name' => ..., 'path' => ..., 'size' => ...]]
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($files, $cancelled, $correlationId);
    }
}

// Date/time picker
class DateTimeRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly string $format = 'Y-m-d H:i:s',
        public readonly ?\DateTimeInterface $minDate = null,
        public readonly ?\DateTimeInterface $maxDate = null,
        public readonly ?\DateTimeInterface $defaultDate = null,
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $correlationId);
    }

    public function getType(): string { return 'datetime'; }
}

// Rich editor (markdown, HTML)
class EditorRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly string $editorType = 'markdown',  // 'markdown', 'html', 'code'
        public readonly ?string $defaultContent = null,
        public readonly ?string $language = null,  // For code editor
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $correlationId);
    }

    public function getType(): string { return 'editor'; }
}
```

### Custom Validation

```php
class ValidatedPromptRequest extends PromptRequest
{
    public function __construct(
        string $question,
        public readonly callable $validator,  // fn(string): bool
        public readonly ?string $errorMessage = null,
        ?string $placeholder = null,
        ?string $defaultValue = null,
        ?string $validation = null,
        bool $multiline = false,
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct(
            $question,
            $placeholder,
            $defaultValue,
            $validation,
            $multiline,
            $context,
            $timeoutMs,
            $correlationId
        );
    }
}
```

---

## Testing Strategy

### Unit Tests

```
tests/PHPUnit/Unit/Feature/Interaction/
  ├─ Message/
  │   ├─ ConfirmRequestSerializationTest.php
  │   ├─ SelectRequestSerializationTest.php
  │   ├─ ChoiceRequestSerializationTest.php
  │   ├─ PromptRequestSerializationTest.php
  │   ├─ ConfirmResponseSerializationTest.php
  │   └─ ...
  ├─ InteractMethodBindingTest.php
  ├─ CorrelationHandlingTest.php
  ├─ CancellationTest.php
  ├─ TimeoutTest.php
  └─ ValidationTest.php
```

### Integration Tests

```
tests/PHPUnit/Integration/Feature/Interaction/
  ├─ ConfirmWorkflowTest.php
  ├─ SelectWorkflowTest.php
  ├─ ChoiceWorkflowTest.php
  ├─ PromptWorkflowTest.php
  ├─ MultipleInteractionsTest.php
  ├─ NestedInteractionsTest.php
  └─ AsyncInteractionTest.php
```

### E2E Tests

```
tests/PHPUnit/E2E/Interaction/
  ├─ CLIAdapterTest.php
  ├─ WebAPIAdapterTest.php
  └─ MachineAgentInteractionTest.php
```

---

## Implementation Phases

### Phase 1: Core Messages (MVP)

**Goal**: Define and test message types
**Effort**: ~20 hours
**Deliverables**:
- InteractionRequest/Response base classes
- Confirm, Select, Choice, Prompt message types
- JSON serialization for all types
- Unit tests for message serialization

### Phase 2: Feature Integration

**Goal**: Integrate with BoundAccess and MessageFeature
**Effort**: ~30 hours
**Deliverables**:
- InteractionFeature class
- `$this->interact()` method binding
- Correlation handling
- Integration tests

### Phase 3: Framework Adapters

**Goal**: Reference implementations for common frameworks
**Effort**: ~25 hours
**Deliverables**:
- CLIInteractionAdapter (symfony/console)
- WebInteractionAdapter (REST API)
- Documentation and examples

### Phase 4: Extensions

**Goal**: Advanced patterns and validation
**Effort**: ~15 hours
**Deliverables**:
- Timeout handling
- Custom validation
- Additional pattern types (file upload, date picker)
- Performance optimization

**Total**: ~90 hours / 2-3 weeks

---

## Security Considerations

### Input Validation

- Validate regex patterns in PromptRequest (prevent ReDoS)
- Sanitize option keys to prevent injection
- Limit array sizes to prevent DoS
- Validate file uploads (extension, size, MIME type)

### Timeout Protection

- Default timeout for all interactions (e.g., 60 seconds)
- Framework should enforce maximum timeout
- Automatic cancellation on timeout

### Access Control

- Framework should verify user authorization
- Consider adding `allowedRoles` to InteractionRequest
- Log all interactions for audit trail

---

## Open Questions

### 1. Timeout Handling Strategy

**Question**: How should timeouts be handled?

**Options**:
- A. Throw exception (forces explicit handling)
- B. Return default value (graceful degradation)
- C. Return special "timed out" response (explicit but verbose)

**Recommendation**: A (throw exception) - forces developers to handle edge case

---

### 2. Persistence During Interactions

**Question**: Can we serialize a machine waiting for interaction?

**Challenge**: Response handler closures in `then()` may not serialize

**Options**:
- A. Prohibit persistence during interaction (document limitation)
- B. Store interaction state separately (complex)
- C. Use named handlers only (restrictive)

**Recommendation**: A for MVP - document as limitation, enhance in Phase 4

---

### 3. Nested Interactions

**Question**: Can an interaction handler trigger another interaction?

**Example**:
```php
$action = yield from $this->interact(new SelectRequest(...));

if ($action === 'delete') {
    // Nested confirmation
    $confirmed = yield from $this->interact(new ConfirmRequest(
        'Are you sure? This cannot be undone.'
    ));
}
```

**Recommendation**: Yes, support nested interactions (already works with correlation)

---

## Alternatives Considered

### 1. Callback-Based API (Rejected)

```php
// Not chosen
$this->confirm('Deploy?', function(bool $result) {
    if ($result) { /* ... */ }
});
```

**Why rejected**: Breaks linear flow, harder to compose, callback hell

---

### 2. Synchronous Blocking API (Rejected)

```php
// Not chosen
$result = $this->confirm('Deploy?');  // Blocks until answer
```

**Why rejected**: Incompatible with async/generator-based architecture

---

### 3. Framework-Specific Integration (Rejected)

```php
// Not chosen
$this->symfonyIO->confirm('Deploy?');
```

**Why rejected**: Couples machines to specific frameworks, breaks portability

---

## Success Metrics

1. **Adoption**: 3+ machines use InteractionFeature within 3 months
2. **Framework Support**: 2+ adapters (CLI, Web) implemented
3. **Code Reduction**: 30% less custom interaction code in machine-agent
4. **Developer Satisfaction**: Positive feedback on API ergonomics
5. **Performance**: <1ms overhead per interaction request

---

## Related Resources

### Internal
- [Abilities API](./abilities-api/abilities-api.md) - Inward invocation pattern
- [MessageFeature Spec](../specs/features/message.yaml) - Correlation infrastructure
- [Machine-Agent Proposal](./machine-agent.md) - Primary use case

### External
- [Inquirer.js](https://github.com/SBoudrias/Inquirer.js) - CLI prompts (inspiration)
- [Laravel Prompts](https://laravel.com/docs/prompts) - Similar pattern
- [GitHub CLI](https://cli.github.com/) - Interactive CLI patterns

---

## Next Steps

1. **Review & Feedback** (1 week)
   - Share with maintainers
   - Gather feedback on patterns and API
   - Refine based on input

2. **Create Specifications** (1 week)
   - Follow spec-driven development
   - Define acceptance criteria for each pattern
   - Create YAML specs

3. **Phase 1 Implementation** (1 week)
   - Implement message types
   - Add serialization
   - Write unit tests

4. **Phase 2-4 Implementation** (2 weeks)
   - Feature integration
   - Framework adapters
   - Documentation

---

**Last Updated**: 2026-01-03
**Status**: Draft - Awaiting review
**Contact**: Open GitHub issue for discussion
