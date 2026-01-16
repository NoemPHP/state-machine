# MessageFeature - UUID-Correlated Request-Response Messaging

## Purpose

MessageFeature provides **call-response messaging** with automatic UUID-based correlation. It enables request-response patterns where the sender doesn't need to know who will respond—the correlation system automatically routes responses back to the original request's handlers.

**Key Value**: Decoupled request-response communication with promise-like `.then()` API.

## Public API

### Message Base Class

All messages extend the abstract `Message` class:

```php
use Noem\State\Feature\Message\Message;

class MyRequest extends Message
{
    public function __construct(
        public readonly string $data,
        ?string $correlationId = null
    ) {
        parent::__construct($correlationId);
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => static::class,
            'correlationId' => $this->correlationId,
            'data' => ['data' => $this->data]
        ];
    }

    protected static function fromData(mixed $data, ?string $correlationId): static
    {
        return new static($data['data'] ?? '', $correlationId);
    }
}
```

### Core Methods

#### `correlationId(): string`

Returns the unique UUID for request-response matching:

```php
$message = new MyRequest('hello');
echo $message->correlationId();  // e.g., "550e8400-e29b-41d4-a716-446655440000"
```

#### `then(callable $handler): self`

Registers response handlers (promise-like pattern):

```php
$request = new MyRequest('hello');
$request
    ->then(fn(Message $response) => echo "Got response!")
    ->then(fn(Message $response) => logResponse($response));

// Multiple handlers supported, all will be called
```

#### `repliesTo(Message $request): bool`

Checks if this message is a response to a given request:

```php
$request = new MyRequest('hello');
$response = $request->createResponse(MyResponse::class, ['result' => 'world']);

$response->repliesTo($request);  // true
$request->repliesTo($request);   // false (same instance)
```

#### `createResponse(string $class, mixed $payload): Message`

Creates a correlated response message:

```php
// In a handler that receives the request
$response = $request->createResponse(MyResponse::class, [
    'result' => 'processed'
]);
// $response has same correlationId as $request
```

#### `deliverResponse(Message $response): void`

Delivers response to all registered `.then()` handlers:

```php
// Usually called internally by MessageFeature
// Can be called manually for testing or custom scenarios
$request->deliverResponse($response);
```

### JSON Serialization

Messages are fully JSON-serializable for persistence and transmission:

```php
$request = new MyRequest('hello');
$json = json_encode($request);

// Reconstruct from JSON
$data = json_decode($json, true);
$reconstructed = Message::fromJson($data);
```

## How MessageFeature Works

### Automatic Subscription Setup

When a Message is dispatched or notified, MessageFeature:

1. Detects the Message payload
2. Creates a temporary subscription filtered by correlation ID
3. When a matching response arrives, delivers it to `.then()` handlers
4. Auto-unsubscribes after first response (first-response-wins)

```php
// Dispatching a message automatically sets up response handling
$region->trigger($request);

// Later, when handler emits response:
$region->notificationChain->call(new Notify($region, $response));
// MessageFeature matches correlationId and calls request's then() handlers
```

### Usage Pattern

```php
// Create and configure request
$request = new MyRequest('process this');
$request->then(function(MyResponse $response) {
    echo "Result: " . $response->result;
});

// Dispatch - subscription is auto-created
$region->trigger($request);

// Somewhere in a state callback, handler creates and emits response
->onAction('processing', function(MyRequest $request) {
    $result = processData($request->data);

    // Create correlated response
    $response = $request->createResponse(MyResponse::class, [
        'result' => $result
    ]);

    // Emit response via notification (MessageFeature routes it back)
    $region->notificationChain->call(new Notify($this->region, $response));
})
```

## Architecture

### Component Overview

```
MessageFeature
    ├── subscribedMessages: WeakMap<Message, true>
    │   └── Tracks which messages have subscriptions to prevent duplicates
    │
    ├── installMessageDispatching()
    │   └── Hooks DispatchAction chain to detect Message payloads
    │
    └── installCorrelationFiltering()
        └── Hooks Notification chain for response routing

Message (abstract)
    ├── correlationId: string (readonly, UUID)
    ├── replyHandlers: callable[] (private)
    ├── pendingResponse: ?Message (for sync patterns)
    │
    ├── then(callable): self
    ├── deliverResponse(Message): void
    ├── repliesTo(Message): bool
    ├── createResponse(class, payload): Message
    │
    └── Abstract: jsonSerialize(), fromData()
```

### Message Lifecycle

```
1. Request created with unique correlationId
    ↓
2. .then() handlers registered
    ↓
3. Request dispatched/notified
    ↓
4. MessageFeature creates correlation subscription
    ↓
5. Handler processes request, creates response
    ↓
6. Response emitted via notification
    ↓
7. MessageFeature matches correlationId
    ↓
8. Response delivered to request's .then() handlers
    ↓
9. Subscription auto-removed (first-response-wins)
```

## Critical Idiosyncrasies

### 1. First-Response-Wins Pattern

Only the first matching response is delivered:

```php
$request->then(fn($r) => echo "Response: {$r->data}");

// First response delivered
$region->notificationChain->call(new Notify($region, $response1));

// Second response IGNORED (subscription already removed)
$region->notificationChain->call(new Notify($region, $response2));
```

### 2. Synchronous Response Caching

If response arrives before `.then()` is called, it's cached:

```php
// Response arrives immediately during dispatch
$region->trigger($request);  // Handler emits response synchronously

// Later, registering handler still works
$request->then(fn($r) => echo "Got it!");  // Called immediately with cached response
```

### 3. WeakMap for Subscription Tracking

Subscriptions use `WeakMap<Message, true>`:
- If request message is garbage collected, subscription tracking auto-removed
- Prevents memory leaks for fire-and-forget patterns

### 4. Response Must Use `createResponse()`

Responses MUST use the request's `createResponse()` to inherit correlation:

```php
// ❌ WRONG - new response has different correlationId
$response = new MyResponse('result');  // Won't match!

// ✅ CORRECT - inherits correlationId from request
$response = $request->createResponse(MyResponse::class, ['result' => 'data']);
```

### 5. Subclasses Must Implement `fromData()`

```php
abstract class Message {
    // Subclasses MUST implement:
    abstract public function jsonSerialize(): mixed;
    abstract protected static function fromData(mixed $data, ?string $correlationId): static;
}
```

## Usage Patterns

### Simple Request-Response

```php
// Define message types
class PingRequest extends Message { /* ... */ }
class PongResponse extends Message { /* ... */ }

// Send request
$ping = new PingRequest();
$ping->then(fn(PongResponse $pong) => echo "Pong received!");
$region->trigger($ping);

// Handler responds
->onAction('listening', function(PingRequest $ping) {
    $pong = $ping->createResponse(PongResponse::class, []);
    $this->region->notificationChain->call(new Notify($this->region, $pong));
})
```

### Chained Processing

```php
$request->then(function(StepOneResponse $r1) use ($region) {
    // Chain to next step
    $step2 = new StepTwoRequest($r1->data);
    $step2->then(fn($r2) => finalizeProcessing($r2));
    $region->trigger($step2);
});
```

### External System Integration

```php
// Serialize request for external transmission
$json = json_encode($request);
sendToExternalSystem($json);

// External system sends response back
$responseJson = receiveFromExternal();
$response = Message::fromJson(json_decode($responseJson, true));
$region->notificationChain->call(new Notify($region, $response));
```

## Relationship to Other Features

| Feature | Relationship | Notes |
|---------|--------------|-------|
| **SubscriptionFeature** | Uses | Built on subscription infrastructure |
| **AbilitiesFeature** | Uses | Abilities use Message for responses |
| **InteractionFeature** | Uses | Interactions extend Message |
| **NotificationChain** | Uses | Responses routed via notifications |

## Files Reference

| File | Purpose |
|------|---------|
| `MessageFeature.php` | Correlation subscription setup |
| `Message.php` | Abstract base class with correlation API |
| `StandardMessage.php` | Generic message implementation |
| `ReflectiveMessageSerialization.php` | Serialization utilities |

## Summary Checklist

When using MessageFeature:

- [ ] Extend `Message` for custom request/response types
- [ ] Implement `jsonSerialize()` and `fromData()` in subclasses
- [ ] Use `.then()` to register response handlers
- [ ] Use `createResponse()` to create correlated responses
- [ ] Emit responses via NotificationChain
- [ ] Remember: first-response-wins (single response per request)
- [ ] Responses can arrive before `.then()` is called (cached)

---

**Spec**: `specs/features/message.yaml`
**Status**: Stable, production-ready
