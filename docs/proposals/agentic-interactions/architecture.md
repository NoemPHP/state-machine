# Agentic Interaction Patterns - Architecture

**Part of**: [Agentic Interactions Proposal](./README.md)
**Focus**: Technical architecture and component relationships

---

## System Architecture

### High-Level Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        State Machine                             │
│                                                                   │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  State Callbacks (onEnter, onAction, etc.)                 │ │
│  │                                                             │ │
│  │  $confirmed = yield from $this->interact(                  │ │
│  │      new ConfirmRequest('Deploy?')                         │ │
│  │  );                                                         │ │
│  └────────────────┬───────────────────────────────────────────┘ │
│                   │                                               │
│                   ▼                                               │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  BoundAccess Chain                                         │ │
│  │  - Intercepts $this->interact() calls                      │ │
│  │  - Managed by InteractionFeature                           │ │
│  └────────────────┬───────────────────────────────────────────┘ │
│                   │                                               │
│                   ▼                                               │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │  InteractionFeature                                        │ │
│  │  - Emits InteractionRequest via NotificationChain          │ │
│  │  - Registers response handler via MessageFeature           │ │
│  │  - Yields until response received                          │ │
│  └────────────────┬───────────────────────────────────────────┘ │
│                   │                                               │
└───────────────────┼───────────────────────────────────────────────┘
                    │
                    │ (Event emission)
                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                    NotificationChain                             │
│  - Global event bus                                              │
│  - Type-filtered by SubscriptionFeature                          │
│  - Delivers to all matching listeners                            │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 │ (Broadcast)
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│              Framework Adapters (Listeners)                      │
│                                                                   │
│  ┌────────────────┐  ┌──────────────┐  ┌───────────────────┐   │
│  │  CLI Adapter   │  │  Web Adapter │  │  Agent Adapter    │   │
│  │                │  │              │  │                   │   │
│  │  - symfony/    │  │  - REST API  │  │  - AI-powered     │   │
│  │    console     │  │  - Queue     │  │    decision       │   │
│  │  - Renders UI  │  │    pending   │  │  - Policy-based   │   │
│  │  - Collects    │  │  - WebSocket │  │    approval       │   │
│  │    input       │  │    notify    │  │                   │   │
│  └────────┬───────┘  └──────┬───────┘  └─────────┬─────────┘   │
│           │                 │                     │              │
│           └─────────────────┼─────────────────────┘              │
│                             │                                    │
└─────────────────────────────┼────────────────────────────────────┘
                              │
                              │ (Response emission)
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    NotificationChain                             │
│  - Emits InteractionResponse                                     │
│  - MessageFeature correlates by ID                               │
│  - Delivers to waiting machine                                   │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 │ (Correlation match)
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                       MessageFeature                             │
│  - Matches correlationId                                         │
│  - Invokes then() handler                                        │
│  - Unsubscribes after delivery                                   │
└────────────────┬────────────────────────────────────────────────┘
                 │
                 │ (Response delivery)
                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                     State Machine                                │
│  - Generator resumes with response value                         │
│  - Continues execution                                           │
└─────────────────────────────────────────────────────────────────┘
```

---

## Component Relationships

### Dependency Graph

```
InteractionFeature
  ├─ requires → ExtendedState (for BoundAccess)
  ├─ requires → SubscriptionFeature (for event emission)
  ├─ requires → MessageFeature (for correlation)
  └─ optional → AsyncFeature (for generator support)

InteractionRequest (Message)
  ├─ extends → Message
  └─ uses → MessageFeature correlation

InteractionResponse (Message)
  ├─ extends → Message
  └─ uses → MessageFeature correlation

Framework Adapters
  ├─ subscribe → NotificationChain
  ├─ listen → InteractionRequest events
  └─ emit → InteractionResponse events
```

### Feature Load Order

**CRITICAL**: Features must load in correct order

```yaml
features:
  # 1. Foundation (ExtendedState must be first)
  - class: Noem\State\Feature\ExtendedState\ExtendedState

  # 2. Communication Infrastructure
  - class: Noem\State\Feature\Subscription\SubscriptionFeature
  - class: Noem\State\Feature\Message\MessageFeature

  # 3. Interaction Support (AFTER dependencies)
  - class: Noem\State\Feature\Interaction\InteractionFeature

  # 4. Optional Enhancements
  - class: Noem\State\Feature\Async\AsyncFeature  # For yield support
```

**Why this order?**
1. ExtendedState → Provides BoundAccess for `$this->interact()`
2. SubscriptionFeature → Enables event emission/listening
3. MessageFeature → Provides correlation for request-response
4. InteractionFeature → Binds `$this->interact()` and handles correlation
5. AsyncFeature → Enables `yield from` in generators

---

## Message Flow Sequence

### Request Flow (Machine → Framework)

```
State Callback
    |
    | $result = yield from $this->interact(new ConfirmRequest(...))
    |
    ▼
BoundAccess::__call('interact', [ConfirmRequest])
    |
    | Intercepts method call
    |
    ▼
InteractionFeature::handleInteraction()
    |
    ├─ Register response handler: $request->then(...)
    |
    ├─ Emit request: NotificationChain->call(Notify($region, $request))
    |
    └─ Yield until response received
         |
         | (Waiting...)
         |
         ▼
Framework Adapter (Listener)
    |
    | on(InteractionRequest $req, Region $source)
    |
    ├─ Detect request type: $req->getType() → 'confirm'
    |
    ├─ Render UI (CLI: prompt, Web: modal, Agent: AI decision)
    |
    ├─ Collect input from user/agent
    |
    └─ Create response: $req->createResponse(ConfirmResponse::class, ...)
```

### Response Flow (Framework → Machine)

```
Framework Adapter
    |
    | $response = $request->createResponse(ConfirmResponse::class, ['confirmed' => true])
    |
    ▼
NotificationChain->call(Notify($region, $response))
    |
    | Emit response event
    |
    ▼
MessageFeature (Listener)
    |
    | Filter: event instanceof Message && event->repliesTo($request)
    |
    ├─ Match correlationId
    |
    └─ Invoke: $request->deliverResponse($response)
         |
         ▼
Message::deliverResponse()
    |
    | foreach ($this->replyHandlers as $handler)
    |
    ▼
InteractionFeature::handleInteraction() ← Response handler
    |
    | $response = $receivedResponse
    |
    └─ Generator resumes
         |
         ▼
State Callback
    |
    | $result = true (from $response->value)
    |
    └─ Continue execution
```

---

## Data Flow

### JSON Serialization (Request)

```php
// In machine
$request = new ConfirmRequest(
    question: 'Deploy to production?',
    context: 'Affects 10,000 users',
    defaultValue: false
);

// Serialized (for logging, transmission, persistence)
$json = json_encode($request);

// Result:
{
  "type": "Noem\\State\\Feature\\Interaction\\ConfirmRequest",
  "correlationId": "550e8400-e29b-41d4-a716-446655440000",
  "data": {
    "question": "Deploy to production?",
    "context": "Affects 10,000 users",
    "defaultValue": false,
    "timeoutMs": null
  }
}
```

### JSON Deserialization (Response)

```php
// From framework/network
$responseData = [
    'type' => 'Noem\\State\\Feature\\Interaction\\ConfirmResponse',
    'correlationId' => '550e8400-e29b-41d4-a716-446655440000',
    'data' => [
        'confirmed' => true,
        'cancelled' => false
    ]
];

// Reconstruct
$response = Message::fromJson($responseData);

// Or via createResponse (preserves correlation)
$response = $request->createResponse(
    ConfirmResponse::class,
    ['confirmed' => true, 'cancelled' => false]
);
```

---

## Framework Adapter Architecture

### CLI Adapter (Symfony Console)

```
┌──────────────────────────────────────────────────┐
│  CLIInteractionAdapter                           │
│                                                   │
│  __construct(Region $region, IO $io)             │
│      └─ $region->on(fn(InteractionRequest) ...)  │
│                                                   │
│  handle(InteractionRequest $req, Region $src)    │
│      ├─ match($req->getType())                   │
│      ├─   'confirm' → renderConfirm()            │
│      ├─   'select'  → renderSelect()             │
│      ├─   'choice'  → renderChoice()             │
│      └─   'prompt'  → renderPrompt()             │
│                                                   │
│  renderConfirm(ConfirmRequest)                   │
│      ├─ QuestionHelper::ask()                    │
│      ├─ Collect yes/no                           │
│      └─ Emit ConfirmResponse                     │
│                                                   │
│  renderSelect(SelectRequest)                     │
│      ├─ ChoiceQuestion::ask()                    │
│      ├─ Display options                          │
│      └─ Emit SelectResponse                      │
│                                                   │
│  renderChoice(ChoiceRequest)                     │
│      ├─ ChoiceQuestion (multiple=true)           │
│      ├─ Checkboxes                               │
│      └─ Emit ChoiceResponse                      │
│                                                   │
│  renderPrompt(PromptRequest)                     │
│      ├─ Question::ask()                          │
│      ├─ Text input (multiline support)           │
│      └─ Emit PromptResponse                      │
└──────────────────────────────────────────────────┘
```

### Web API Adapter (REST)

```
┌──────────────────────────────────────────────────┐
│  WebInteractionAdapter                           │
│                                                   │
│  Properties:                                      │
│    - array $pendingInteractions                  │
│    - Region $region                              │
│                                                   │
│  __construct(Region $region)                     │
│      └─ $region->on(fn(InteractionRequest) ...)  │
│                                                   │
│  enqueue(InteractionRequest $req)                │
│      └─ $pendingInteractions[$req->id] = [       │
│            'request' => $req,                     │
│            'timestamp' => time(),                 │
│            'status' => 'pending'                  │
│         ]                                         │
│                                                   │
│  API Endpoints:                                   │
│                                                   │
│  GET /interactions/pending                       │
│      └─ return array_filter(..., 'pending')      │
│                                                   │
│  GET /interactions/{id}                          │
│      └─ return $pendingInteractions[$id]         │
│                                                   │
│  POST /interactions/{id}/respond                 │
│      ├─ Validate response data                   │
│      ├─ Create response via createResponse()     │
│      ├─ Emit via NotificationChain                │
│      └─ Mark status = 'answered'                 │
│                                                   │
│  DELETE /interactions/{id}                       │
│      └─ Cancel interaction (timeout/user)        │
└──────────────────────────────────────────────────┘
```

### Agent Adapter (AI-Powered)

```
┌──────────────────────────────────────────────────┐
│  AgentInteractionAdapter                         │
│                                                   │
│  Properties:                                      │
│    - AiFeature $ai                               │
│    - PolicyEngine $policy                        │
│    - Region $region                              │
│                                                   │
│  __construct(Region $region, AiFeature $ai, ...) │
│      └─ $region->on(fn(InteractionRequest) ...)  │
│                                                   │
│  handle(InteractionRequest $req, Region $src)    │
│      ├─ Analyze request context                  │
│      ├─ Apply policy rules                       │
│      └─ Delegate to AI or auto-respond           │
│                                                   │
│  handleConfirm(ConfirmRequest)                   │
│      ├─ Policy check: is_allowed($req->context)  │
│      ├─ If uncertain → Ask AI                    │
│      ├─ AI prompt: "Should I {question}?"        │
│      └─ Emit ConfirmResponse                     │
│                                                   │
│  handleSelect(SelectRequest)                     │
│      ├─ AI analyzes options                      │
│      ├─ Ranks by: cost, performance, risk        │
│      ├─ Selects best option                      │
│      └─ Emit SelectResponse                      │
│                                                   │
│  handleChoice(ChoiceRequest)                     │
│      ├─ AI evaluates each option                 │
│      ├─ Respects min/max constraints             │
│      ├─ Selects recommended options              │
│      └─ Emit ChoiceResponse                      │
│                                                   │
│  handlePrompt(PromptRequest)                     │
│      ├─ AI generates response                    │
│      ├─ Validates against regex pattern          │
│      └─ Emit PromptResponse                      │
└──────────────────────────────────────────────────┘
```

---

## Correlation Mechanism

### UUID-Based Matching

**Key Insight**: Uses existing MessageFeature correlation infrastructure

```php
// Request creation
$request = new ConfirmRequest('Deploy?');
// → Generates UUID: "550e8400-e29b-41d4-a716-446655440000"

// Response creation (via createResponse)
$response = $request->createResponse(
    ConfirmResponse::class,
    ['confirmed' => true]
);
// → Inherits UUID: "550e8400-e29b-41d4-a716-446655440000"

// Matching (automatic)
MessageFeature detects:
    $response->repliesTo($request) === true
    → Delivers to $request->then() handlers
```

### Correlation Flow

```
┌─────────────────────────────────────────────────────────────┐
│  Request                                                     │
│                                                              │
│  ID: 550e8400-e29b-41d4-a716-446655440000                   │
│  Type: ConfirmRequest                                       │
│  Question: "Deploy?"                                        │
│  Handlers: [fn($response) { ... }]                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ (Emitted)
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  NotificationChain (global event bus)                       │
│                                                              │
│  Listeners:                                                 │
│    - CLIAdapter→handle()                                    │
│    - WebAdapter→enqueue()                                   │
│    - AgentAdapter→handle()                                  │
│                                                              │
│  All receive event (type-filtered)                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ (First responder)
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  Response                                                    │
│                                                              │
│  ID: 550e8400-e29b-41d4-a716-446655440000  ← SAME ID        │
│  Type: ConfirmResponse                                      │
│  Value: true                                                │
│  Cancelled: false                                           │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ (Emitted)
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  MessageFeature (correlation matcher)                       │
│                                                              │
│  foreach (subscribedMessages as $request) {                 │
│      if ($response->repliesTo($request)) {                  │
│          $request->deliverResponse($response);              │
│          unsubscribe();  // First-response-wins             │
│      }                                                       │
│  }                                                           │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ (Delivery)
                              ▼
┌─────────────────────────────────────────────────────────────┐
│  Request Handler                                             │
│                                                              │
│  fn($response) {                                            │
│      // $response === ConfirmResponse(true)                │
│      $this->responseReceived = $response->value;            │
│  }                                                           │
└─────────────────────────────────────────────────────────────┘
```

---

## Error Handling

### Timeout Flow

```
InteractionFeature::handleInteraction()
    |
    ├─ Start timeout: setTimeout($request->timeoutMs ?? 60000)
    |
    ├─ Yield until response OR timeout
    |
    └─ if (timeout) {
         ├─ Cancel subscription
         ├─ throw InteractionCancelledException('Timeout')
         └─ Machine can catch and handle
       }
```

### Cancellation Flow

```
Framework Adapter
    |
    | User presses ESC or cancels
    |
    ▼
$response = $request->createResponse(
    ResponseClass::class,
    ['value' => null, 'cancelled' => true]
);

InteractionFeature
    |
    | if ($response->cancelled) {
    |     throw InteractionCancelledException('User cancelled')
    | }
```

### Multiple Responses

**First-Response-Wins Policy**:

```
MessageFeature auto-unsubscribes after first matching response.

Timeline:
    T0: Request emitted, subscription created
    T1: Response A received → Delivered, unsubscribed
    T2: Response B received → Ignored (no subscription)
```

---

## Extension Points

### Custom Interaction Pattern

```php
// 1. Define request
class FileUploadRequest extends InteractionRequest
{
    public function __construct(
        string $question,
        public readonly array $allowedExtensions = [],
        public readonly int $maxSizeBytes = 10485760,
        ?string $context = null,
        ?int $timeoutMs = null,
        ?string $correlationId = null
    ) {
        parent::__construct($question, $context, $timeoutMs, $correlationId);
    }

    public function getType(): string { return 'file_upload'; }
}

// 2. Define response
class FileUploadResponse extends InteractionResponse
{
    public function __construct(
        public readonly array $files,  // [['name' => ..., 'path' => ...]]
        bool $cancelled = false,
        ?string $correlationId = null
    ) {
        parent::__construct($files, $cancelled, $correlationId);
    }
}

// 3. Framework adapter handles new type
match($request->getType()) {
    'file_upload' => $this->handleFileUpload($request),
    // ...existing types
}
```

### Custom Middleware

```php
// Add logging middleware to InteractionFeature
class InteractionLoggingMiddleware
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(ChainMail $chainMail): void
    {
        $chainMail->use(function(BoundAccess $boundAccess) {
            $boundAccess->link(function(BoundAccessParams $params, callable $next) {
                if ($params->name === 'interact') {
                    $request = $params->payload[0];
                    $this->logger->info('Interaction requested', [
                        'type' => $request->getType(),
                        'question' => $request->question,
                        'correlationId' => $request->correlationId()
                    ]);
                }

                return $next($params);
            });
        });
    }
}
```

---

## Performance Considerations

### Overhead Analysis

**Per Interaction**:
- Message creation: ~0.1ms
- UUID generation: ~0.01ms
- Event emission: ~0.2ms (depends on listener count)
- Correlation matching: ~0.1ms (hash lookup)
- **Total overhead**: ~0.5ms

**Optimization Strategies**:
1. **Batch interactions**: Group related questions
2. **Lazy validation**: Defer schema checks until response
3. **Cache type info**: Reuse reflection results
4. **Async responses**: Don't block on network I/O

---

## Security Architecture

### Input Validation

```
Framework Adapter (untrusted input)
    |
    | Validate response data against request schema
    |
    ├─ ConfirmRequest: ensure boolean
    ├─ SelectRequest: ensure key exists in options
    ├─ ChoiceRequest: validate min/max selections
    └─ PromptRequest: validate regex pattern
         |
         ▼
$response = $request->createResponse(..., $validatedData);
```

### Timeout Enforcement

```
InteractionFeature
    |
    | Default: 60 seconds
    | Max: 300 seconds (configurable)
    |
    └─ Prevents indefinite blocking
```

### Audit Trail

```
All interactions JSON-serializable → Log to audit system

{
  "timestamp": "2026-01-03T10:30:00Z",
  "machineId": "machine-agent-123",
  "interactionType": "confirm",
  "question": "Deploy to production?",
  "context": "Affects 10,000 users",
  "response": {
    "confirmed": true,
    "respondedBy": "user@example.com",
    "respondedAt": "2026-01-03T10:30:15Z"
  }
}
```

---

## Comparison with Abilities API

### Architecture Similarities

Both use:
- Message-based communication
- Correlation for request-response
- ChainMail for middleware
- BoundAccess for `$this->*()` API

### Key Differences

| Aspect | Abilities API | Interaction Patterns |
|--------|---------------|----------------------|
| **Direction** | External → Machine | Machine → External |
| **Trigger** | External call | Internal `$this->interact()` |
| **Purpose** | Execute logic | Request information |
| **Middleware** | InvokeAbility Chain | BoundAccess + MessageFeature |
| **Response** | Business data | User input |
| **Validation** | Parameter schema | Response schema |

### Complementary Design

```
External Agent
    ↓
[Abilities API] → Execute machine logic
    ↓
Machine needs input
    ↓
[Interaction Patterns] → Request from agent
    ↓
Agent responds
    ↓
[Abilities API] → Continue execution
```

---

## Summary

**Key Architectural Principles**:

1. ✅ **Leverage existing infrastructure** - Built on MessageFeature, SubscriptionFeature
2. ✅ **Minimal new abstractions** - Only InteractionRequest/Response + Feature
3. ✅ **Framework-agnostic** - Clean separation via event bus
4. ✅ **Type-safe** - Schema validation at boundaries
5. ✅ **Extensible** - Easy to add new patterns
6. ✅ **Auditable** - JSON-serializable for logging
7. ✅ **Performant** - <1ms overhead per interaction

**Integration Points**:
- BoundAccess → `$this->interact()` method
- MessageFeature → Correlation and response delivery
- SubscriptionFeature → Event emission and listening
- ExtendedState → Context storage (optional)

**Next**: See [interaction-patterns.md](./interaction-patterns.md) for detailed specifications.
